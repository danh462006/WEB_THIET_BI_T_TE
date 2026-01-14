<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

session_start();

if (isset($_POST['send'])) {
    $gmail = $_POST['gmail'];
    $ma = rand(100000, 999999);
    $_SESSION['verification_code'] = (string)$ma;

    $mail = new PHPMailer(true);
    try {
       
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        $mail->Username = 'nguyenthanhdanh4626@gmail.com'; 
        $mail->Password = 'mxkb emmn ebul dyuy';  
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('nguyenthanhdanh4626@gmail.com', 'Công Ty TNHH Thiết Bị Y Tế Đức Phương');
        $mail->addAddress($gmail);

        $mail->CharSet = 'UTF-8';


        $mail->isHTML(true);
        $mail->Subject = 'Mã xác nhận ';
        $mail->Body    = "Mã xác nhận của bạn là: <b>$ma</b>";

        $mail->send();
        echo "Đã gửi mã xác nhận tới Gmail: $gmail";
    } catch (Exception $e) {
        echo " Lỗi gửi mail: {$mail->ErrorInfo}";
    }
}
?>