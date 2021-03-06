<?php
namespace Dasauser;

use Dasauser\Exceptions\FileNotFoundException;
use Dasauser\Exceptions\UnknownException;

/**
 * Another some shitty builder for css and js files
 * Class ShittyBuilder
 * @package Dasauser
 */
class ShittyBuilder
{
    /**
     * @var $assets_dir
     */
    protected static $assets_dir;
    /**
     * @var $built_dir
     */
    protected static $built_dir;
    /**
     * @var $built_map
     */
    protected static $built_map;

    /**
     * Binding function
     * @param string $assets_dir
     * @param array $build_map
     * @param string $built_dir
     * @param bool $minify
     * @throws FileNotFoundException
     * @throws UnknownException
     */
    public static function checkAndConnect(string $assets_dir, array $build_map = [], string $built_dir = '', bool $minify = true) : void
    {
        static::check($assets_dir, $build_map, $built_dir, $minify);
        static::connect();
    }

    /**
     * Function checking files for changes, updating and creating build files
     * @param string $assets_dir
     * @param array $build_map
     * @param string $built_dir
     * @param bool $minify
     * @throws FileNotFoundException
     */
    public static function check(string $assets_dir, array $build_map = [], string $built_dir = '', bool $minify = true) : void
    {
        $built_dir = $built_dir === '' ? 'public/build' : $built_dir;
        static::$assets_dir = $assets_dir;
        static::$built_dir = $built_dir;
        $build_map_array = $build_map === [] ? static::getFilesMap() : $build_map;
        if (!file_exists(static::$built_dir)) {
            mkdir(static::$built_dir, 0755, true);
        }
        $built_map = static::getBuiltMap();
        $updated_somebody = false;
        foreach ($build_map_array as $item) {
            $updated = false;
            $file_name = static::$assets_dir . "/$item";
            if (file_exists($file_name)) {
                $md_hash = md5_file($file_name);
                if ($md_hash !== $built_map[$item]['hash']) {
                    $built_map[$item]['hash'] = $md_hash;
                    if (isset($built_map[$item]['built_file'])) {
                        static::deleteOld($built_map[$item]['built_file']);
                    }
                    $updated = true;
                } else {
                    if (empty($built_map[$item]['built_file'])) {
                        $updated = true;
                    } else {
                        $built_file = static::$built_dir . '/' . $built_map[$item]['built_file'];
                        if (!file_exists($built_file)) {
                            static::copy($file_name, $built_map[$item]['built_file']);
                        }
                    }
                }
                if ($updated) {
                    $updated_somebody = true;
                    $new_file = static::copy($file_name);
                    if ($new_file !== '') {
                        $built_map[$item]['built_file'] = $new_file;
                        if ($minify){
                            static::minifyContent(static::$built_dir . '/' . $built_map[$item]['built_file']);
                        }
                    }
                }
            } else {
                throw new FileNotFoundException('File ' . $file_name . ' not found');
            }
        }
        if ($updated_somebody) {
            static::write($built_map);
        }
    }

    /**
     * Function connecting style and script files
     * @throws UnknownException
     */
    public static function connect() : void
    {
        foreach (static::getBuiltMap() as $file) {
            $file = static::$built_dir . "/{$file['built_file']}";
            if (file_exists($file)) {
                $ext = substr($file, strripos($file, '.') + 1);
                switch ($ext) {
                    case 'js':
                        echo "<script type='text/javascript' src='$file'></script>";
                        break;
                    case 'css':
                        echo "<link rel='stylesheet' href='$file'>";
                        break;
                    default:
                        throw new UnknownException('Unknown file extension: ' . $ext);
                        break;
                }
            }
        }
    }

    /**
     * Function getting files map
     * @return array
     * @throws FileNotFoundException
     */
    protected static function getFilesMap() : array
    {
        $file_map = static::$assets_dir . '/map.php';
        if (file_exists($file_map)) {
            return require_once($file_map);
        }
        throw new FileNotFoundException('Set custom map file or create ' . static::$assets_dir . '/map.php');
    }

    /**
     * Function getting built file map
     * @return array
     */
    protected static function getBuiltMap() : array
    {
        static::$built_map = static::$built_dir . '/map.json';
        if (file_exists(static::$built_map)) {
            return json_decode(file_get_contents(static::$built_map), true);
        }
        return [];
    }

    /**
     * Function creating new build files
     * If old name exist, but old file not exist, then function creates new file with old name
     * @param string $file_name
     * @param string $new_file
     * @return string
     */
    protected static function copy(string $file_name, string $new_file = '') : string
    {
        $new_file = $new_file === '' ? substr(md5(uniqid()), -15) . substr($file_name, strripos($file_name, '.')) : $new_file;
        $new_file_path = static::$built_dir . '/' . $new_file;
        if (!copy($file_name, $new_file_path)) {
            return '';
        }
        do {
            $file_exist = file_exists($new_file_path);
        } while (!$file_exist);
        return $new_file;
    }

    /**
     * Function updating (minifying) content
     * Now I am using the existing solution, but next time I will create my own
     * @param string $file
     */
    protected static function minifyContent(string $file) : void
    {
        $file_content = file_get_contents($file);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://minify.minifier.org/',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'source=' . $file_content . '&type=' . substr($file, strripos($file, '.') + 1),
            CURLOPT_RETURNTRANSFER => true
        ]);
        $result = curl_exec($curl);
        file_put_contents($file, json_decode($result, true)['minified']);
    }

    /**
     * Function write built map
     * @param array $data
     */
    protected static function write(array $data) : void
    {
        file_put_contents(static::$built_map, json_encode($data));
    }

    /**
     * Function deleting old useless files
     * @param string $old_file_name
     */
    protected static function deleteOld(string $old_file_name) : void
    {
        $old_file_name = static::$built_dir . "/$old_file_name";
        if (file_exists($old_file_name)) {
            unlink($old_file_name);
        }
    }
}