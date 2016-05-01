<?php
/**
 * run on the command line like: php client.php
 * php ~/laravel/ilogme-client/client.php
 * 
 */

$user_name = 'Laoyuan'; //your username
$pic_key = '6d23630b57c878a4b3d01215254dd825'; //get it on http://ilogme.com/u/settings
$per_seconds = 40;  //how many seconds to take a pic 
$width = 480;  //width of pic
$local_backup = true;  //save local copy or not
$url_post = 'http://www.ilogme.com/' . $user_name . '/savepic';

$dir_pic = __DIR__ . '/' . 'pic';
if (!file_exists($dir_pic)) {
    mkdir($dir_pic);
}
$pic_screen = $dir_pic . "/screen.png";
$pic_camera = $dir_pic . "/camera.jpg";
$width_little = $width / 6;
echo date('Ymd H:i:s', time()) . ' Start' . PHP_EOL;

while(1) {
    if (file_exists($pic_screen)) {
        unlink($pic_screen);
    }
    if (file_exists($pic_camera)) {
        unlink($pic_camera);
    }
    $time = time();
    echo date('Ymd H:i:s ', $time);

    //mac
    if (PHP_OS === 'Darwin') {
        $shell_screen = __DIR__ . '/screencapture -x ' . $pic_screen;
        $r_screen = shell_exec($shell_screen);
        $im_screen = imagecreatefrompng($pic_screen);

        $shell_camera = __DIR__ . '/snap ' . $pic_camera;
        $r_camera = shell_exec($shell_camera);
        $im_camera = imagecreatefromjpeg($pic_camera);
        //increase brightness
        imagefilter($im_camera, IMG_FILTER_BRIGHTNESS, 64);
    }
    //other os, untest
    else {
        $im_screen = imagegrabscreen();
        $im_camera = false;
    }

    //resize
    if ($im_screen) {
        $width_screen = imagesx($im_screen);
        $height_screen = imagesy($im_screen);
        $height = $height_screen * $width / $width_screen;
        $pic = imagecreatetruecolor($width, $height);
        imagecopyresampled($pic, $im_screen, 0, 0, 0, 0, $width, $height, $width_screen, $height_screen);
    }
    else {
        echo 'Error! got screen pic error.' . PHP_EOL;
        sleep(10);
        continue;
    }

    if ($im_camera) {
        $width_camera = imagesx($im_camera);
        $height_camera = imagesy($im_camera);
        $height_little = $height_camera * $width_little / $width_camera;
        imagecopyresampled($pic, $im_camera, $width - $width_little, $height - $height_little, 0, 0, $width_little, $height_little, $width_camera, $height_camera);
    }

    //get pic data without read from file
    ob_start();
    imagejpeg($pic);
    $pic_data = ob_get_contents();
    ob_end_clean();

    //write to file
    if ($local_backup) { 
        $dir_file_date = $dir_pic . '/' . date('Ymd', $time);
        if (!file_exists($dir_file_date)) {
            mkdir($dir_file_date);
        }
        $handle = fopen($dir_file_date. '/' . $time . '.jpg', 'a');
        fwrite($handle, $pic_data);
        fclose($handle);
    }
#header("Content-type: image/jpeg");exit($pic_data);

    //encrypt
    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
    $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
    $ciphertext = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $pic_key, $pic_data, MCRYPT_MODE_CBC, $iv);
    $ciphertext = $iv . $ciphertext;
    $ar_data = ['data' => base64_encode($ciphertext)];

    //post to server
    $opts = array('http' => array(
        'method'  => 'POST',
        'header'  => "Content-Type: application/json",
        'content' => json_encode($ar_data),
        'timeout' => 60,
    ));
    $context  = stream_context_create($opts);
    $result_post = file_get_contents($url_post, false, $context, -1, 40000);
    echo $result_post . ' ';

    if ($result_post !== 'OK.') {
        sleep(10);
        echo 'Break 10 seconds.' . PHP_EOL;
    }
    else {
        $time_sleep = rand($per_seconds * 0.5, $per_seconds * 1.5);
        echo 'Sleep ' . $time_sleep . ' seconds.' . PHP_EOL;
        sleep($time_sleep);
    }
}

