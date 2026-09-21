<?php
/** File upload handling (replaces multer). */
final class Uploader
{
    public const IMAGES = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif', 'bmp', 'ico'];
    public const MEDIA  = ['mp4', 'webm', 'mov', 'mp3', 'wav', 'ogg', 'm4a'];
    public const MAX    = 25 * 1024 * 1024;

    /**
     * @param array|null $file  entry from $_FILES
     * @param string     $kind  images|media|any
     * @return array{ok:bool,url?:string,path?:string,error?:string}
     */
    public static function save(?array $file, string $kind = 'images', string $subdir = ''): array
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'no-file'];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'upload-failed-' . $file['error']];
        }
        if ($file['size'] > self::MAX) {
            return ['ok' => false, 'error' => 'file-too-large'];
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $allowed = $kind === 'images' ? self::IMAGES : ($kind === 'media' ? array_merge(self::IMAGES, self::MEDIA) : array_merge(self::IMAGES, self::MEDIA, ['txt', 'md', 'json', 'pdf', 'zip']));
        if (!in_array($ext, $allowed, true)) {
            return ['ok' => false, 'error' => 'invalid-type'];
        }
        if (!is_uploaded_file($file['tmp_name']) && php_sapi_name() !== 'cli') {
            return ['ok' => false, 'error' => 'invalid-upload'];
        }

        $subdir = trim($subdir, '/');
        $dir = NH_UPLOADS . ($subdir ? '/' . $subdir : '');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $name = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $path = $dir . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            // cli / built-in server fallback
            if (!@rename($file['tmp_name'], $path)) {
                @copy($file['tmp_name'], $path);
            }
        }
        $url = '/storage/uploads/' . ($subdir ? $subdir . '/' : '') . $name;
        return ['ok' => true, 'url' => $url, 'path' => $path, 'name' => $name];
    }

    /** Pick the first present upload among several fields. */
    public static function first(Request $req, array $fields, string $kind = 'images', string $subdir = ''): array
    {
        foreach ($fields as $f) {
            $file = $req->file($f);
            if ($file) {
                $res = self::save($file, $kind, $subdir);
                if ($res['ok']) return $res;
            }
        }
        return ['ok' => false, 'error' => 'no-file'];
    }
}
