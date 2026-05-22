<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../modules/user-management/controller.php';

const TRACS_AVATAR_UPLOAD_MAX = 5242880;
const TRACS_AVATAR_FINAL_MAX = 1258291;

function avatar_fail_upload(string $message, int $code = 400): void {
    fail($message, $code);
}

function avatar_storage_dir(): string {
    $dir = realpath(__DIR__ . '/../uploads');
    if ($dir === false) {
        avatar_fail_upload('Upload storage is not available.', 500);
    }
    $avatarDir = $dir . DIRECTORY_SEPARATOR . 'avatars';
    if (!is_dir($avatarDir) && !mkdir($avatarDir, 0755, true)) {
        avatar_fail_upload('Avatar storage is not writable.', 500);
    }
    return $avatarDir;
}

function avatar_public_path(string $fileName): string {
    return '/uploads/avatars/' . $fileName;
}

function avatar_delete_public_path(string $path): void {
    $path = tracs_user_avatar_path(['avatar_path' => $path]);
    if ($path === '') {
        return;
    }
    $base = realpath(__DIR__ . '/../uploads/avatars');
    if ($base === false) {
        return;
    }
    $candidate = realpath(__DIR__ . '/..' . $path);
    if ($candidate && str_starts_with($candidate, $base . DIRECTORY_SEPARATOR) && is_file($candidate)) {
        @unlink($candidate);
    }
}

function avatar_image_resource(string $tmpName, string $mime) {
    return match ($mime) {
        'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($tmpName) : false,
        'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($tmpName) : false,
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpName) : false,
        default => false,
    };
}

function avatar_store_uploaded_file(array $file, int $targetUserId): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        avatar_fail_upload('Choose a valid image to upload.');
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > TRACS_AVATAR_UPLOAD_MAX) {
        avatar_fail_upload('Profile pictures must be 5MB or smaller.');
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        avatar_fail_upload('Invalid upload.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmpName);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        avatar_fail_upload('Only JPG, JPEG, PNG, and WEBP profile pictures are supported.');
    }

    $info = @getimagesize($tmpName);
    if (!$info || !isset($info['mime']) || !isset($allowed[(string)$info['mime']])) {
        avatar_fail_upload('The uploaded file is not a readable image.');
    }

    $avatarDir = avatar_storage_dir();
    $token = bin2hex(random_bytes(12));
    $baseName = 'avatar_' . $targetUserId . '_' . $token;
    $resource = function_exists('imagecreatetruecolor') ? avatar_image_resource($tmpName, $mime) : false;

    if ($resource !== false) {
        $width = imagesx($resource);
        $height = imagesy($resource);
        if ($width < 64 || $height < 64) {
            imagedestroy($resource);
            avatar_fail_upload('Profile pictures must be at least 64x64px.');
        }
        $sizeOut = 512;
        $canvas = imagecreatetruecolor($sizeOut, $sizeOut);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $sizeOut, $sizeOut, $transparent);
        imagecopyresampled($canvas, $resource, 0, 0, 0, 0, $sizeOut, $sizeOut, $width, $height);

        $canWebp = function_exists('imagewebp');
        $extension = $canWebp ? 'webp' : 'jpg';
        $dest = $avatarDir . DIRECTORY_SEPARATOR . $baseName . '.' . $extension;
        $saved = $canWebp ? imagewebp($canvas, $dest, 82) : imagejpeg($canvas, $dest, 82);
        imagedestroy($canvas);
        imagedestroy($resource);
        if (!$saved || !is_file($dest)) {
            avatar_fail_upload('Unable to save profile picture.', 500);
        }
        if (filesize($dest) > TRACS_AVATAR_FINAL_MAX) {
            @unlink($dest);
            avatar_fail_upload('Optimized profile picture is too large.');
        }
        @chmod($dest, 0644);
        return avatar_public_path(basename($dest));
    }

    if ($size > TRACS_AVATAR_FINAL_MAX) {
        avatar_fail_upload('Optimized profile picture is too large.');
    }
    $dest = $avatarDir . DIRECTORY_SEPARATOR . $baseName . '.' . $allowed[$mime];
    if (!move_uploaded_file($tmpName, $dest)) {
        avatar_fail_upload('Unable to save profile picture.', 500);
    }
    @chmod($dest, 0644);
    return avatar_public_path(basename($dest));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}
if (!tracs_column_exists($conn, 'tracs_users', 'avatar_path')) {
    fail('Run the avatar profile picture migration before saving photos.', 409);
}

$targetUserId = (int)($_POST['target_user_id'] ?? $uid);
$action = (string)($_POST['action'] ?? 'upload');
$UM = new UserManagementController($conn, $uid);
$target = $UM->getUser($targetUserId);
if (!$target) {
    fail('User not found', 404);
}

try {
    if ($action === 'remove') {
        $oldPath = (string)($target['avatar_path'] ?? '');
        $result = $UM->updateProfilePicture($targetUserId, null);
        avatar_delete_public_path($oldPath);
        ok(['user_id' => $targetUserId, 'avatar_url' => '', 'initials' => tracs_user_initials((string)($result['user']['display_name'] ?? ''), (string)($result['user']['email'] ?? 'U'))], $result['message']);
    }

    if ($action !== 'upload') {
        fail('Unknown action.');
    }

    $newPath = avatar_store_uploaded_file($_FILES['avatar'] ?? [], $targetUserId);
    $oldPath = (string)($target['avatar_path'] ?? '');
    $result = $UM->updateProfilePicture($targetUserId, $newPath);
    avatar_delete_public_path($oldPath);
    ok([
        'user_id' => $targetUserId,
        'avatar_url' => tracs_user_avatar_url(['avatar_path' => $newPath]),
        'avatar_path' => $newPath,
        'initials' => tracs_user_initials((string)($result['user']['display_name'] ?? ''), (string)($result['user']['email'] ?? 'U')),
    ], $result['message']);
} catch (Throwable $e) {
    if (!empty($newPath ?? '')) {
        avatar_delete_public_path((string)$newPath);
    }
    if ($e instanceof InvalidArgumentException) {
        fail($e->getMessage(), 400);
    }
    error_log('TRACS avatar update failed: ' . $e->getMessage());
    fail('Profile picture could not be updated.', 500);
}
