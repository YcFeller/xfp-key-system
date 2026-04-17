<?php
require_once __DIR__ . '/load_env.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

class MailSender {
    private $mailer;
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $from_email;
    private $from_name;

    public function __construct() {
        $this->smtp_host = env('SMTP_HOST', '');
        $this->smtp_port = (int) env('SMTP_PORT', '465');
        $this->smtp_username = env('SMTP_USERNAME', '');
        $this->smtp_password = env('SMTP_PASSWORD', '');
        $this->from_email = env('MAIL_FROM_ADDRESS', '');
        $this->from_name = env('MAIL_FROM_NAME', 'XFP密钥系统');

        if ($this->smtp_host === '' || $this->smtp_username === '' || $this->smtp_password === '' || $this->from_email === '') {
            throw new Exception('邮件未配置：请在 .env 中填写 SMTP_HOST、SMTP_PORT、SMTP_USERNAME、SMTP_PASSWORD、MAIL_FROM_ADDRESS。');
        }

        $this->mailer = new PHPMailer(true);
        $this->setupSMTP();
    }

    private function setupSMTP() {
        try {
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->smtp_host;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->smtp_username;
            $this->mailer->Password = $this->smtp_password;
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $this->mailer->Port = $this->smtp_port;
            $this->mailer->CharSet = 'UTF-8';

            $this->mailer->setFrom($this->from_email, $this->from_name);
        } catch (Exception $e) {
            throw new Exception("邮件配置失败: " . $e->getMessage());
        }
    }

    /**
     * 发送邮件
     * @param string $to_email 收件人邮箱
     * @param string $to_name 收件人姓名
     * @param string $subject 邮件主题
     * @param string $body 邮件内容
     * @param bool $is_html 是否为HTML格式
     * @return bool
     */
    public function sendMail($to_email, $to_name, $subject, $body, $is_html = true) {
        try {
            $this->mailer->clearAddresses();

            $this->mailer->addAddress($to_email, $to_name);

            $this->mailer->isHTML($is_html);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;

            $this->mailer->SMTPDebug = 0;
            $this->mailer->Debugoutput = function($str, $level) {
                error_log("SMTP Debug: " . $str);
            };

            $result = $this->mailer->send();

            if ($result) {
                error_log("邮件发送成功: 发送到 {$to_email}");
                return true;
            }
            error_log("邮件发送失败: 未知错误");
            return false;
        } catch (Exception $e) {
            $error_msg = "邮件发送失败: " . $e->getMessage();
            error_log($error_msg);
            error_log("SMTP错误详情: " . $this->mailer->ErrorInfo);

            throw new Exception($error_msg . " (SMTP: " . $this->mailer->ErrorInfo . ")");
        }
    }

    /**
     * 发送验证码邮件
     * @param string $to_email 收件人邮箱
     * @param string $to_name 收件人姓名
     * @param string $verification_code 验证码
     * @param string $type 验证码类型 (password_reset, email_verify, etc.)
     * @return bool
     */
    public function sendVerificationCode($to_email, $to_name, $verification_code, $type = 'password_reset') {
        $subject_map = [
            'password_reset' => '密码重置验证码 - XFP密钥获取系统',
            'email_verify' => '邮箱验证码 - XFP密钥获取系统',
            'account_security' => '账户安全验证码 - XFP密钥获取系统'
        ];

        $subject = $subject_map[$type] ?? '验证码 - XFP密钥获取系统';

        $body = $this->getVerificationEmailTemplate($to_name, $verification_code, $type);

        return $this->sendMail($to_email, $to_name, $subject, $body);
    }

    /**
     * 获取验证码邮件模板
     * @param string $name 收件人姓名
     * @param string $code 验证码
     * @param string $type 验证码类型
     * @return string
     */
    private function getVerificationEmailTemplate($name, $code, $type) {
        $action_text = [
            'password_reset' => '密码重置',
            'email_verify' => '邮箱验证',
            'account_security' => '账户安全验证'
        ];

        $action = $action_text[$type] ?? '验证';

        return '
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>验证码</title>
            <style>
                body {
                    font-family: "Microsoft YaHei", Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    background-color: #f4f4f4;
                    margin: 0;
                    padding: 20px;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: #ffffff;
                    border-radius: 10px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    overflow: hidden;
                }
                .header {
                    background: linear-gradient(135deg, #3176FF 0%, #6C47FF 100%);
                    color: white;
                    padding: 30px;
                    text-align: center;
                }
                .header h1 {
                    margin: 0;
                    font-size: 24px;
                    font-weight: bold;
                }
                .content {
                    padding: 40px 30px;
                }
                .verification-code {
                    background-color: #f8f9fa;
                    border: 2px dashed #3176FF;
                    border-radius: 8px;
                    padding: 20px;
                    text-align: center;
                    margin: 20px 0;
                }
                .code {
                    font-size: 32px;
                    font-weight: bold;
                    color: #3176FF;
                    letter-spacing: 5px;
                    font-family: "Courier New", monospace;
                }
                .footer {
                    background-color: #f8f9fa;
                    padding: 20px 30px;
                    text-align: center;
                    color: #666;
                    font-size: 14px;
                }
                .warning {
                    background-color: #fff3cd;
                    border: 1px solid #ffeaa7;
                    border-radius: 5px;
                    padding: 15px;
                    margin: 20px 0;
                    color: #856404;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>XFP密钥获取系统</h1>
                </div>
                <div class="content">
                    <h2>您好，' . htmlspecialchars($name) . '！</h2>
                    <p>您正在进行' . $action . '操作，请使用以下验证码完成验证：</p>

                    <div class="verification-code">
                        <div class="code">' . $code . '</div>
                    </div>

                    <div class="warning">
                        <strong>安全提醒：</strong>
                        <ul style="margin: 10px 0; padding-left: 20px;">
                            <li>验证码有效期为10分钟</li>
                            <li>请勿将验证码泄露给他人</li>
                            <li>如非本人操作，请忽略此邮件</li>
                        </ul>
                    </div>

                    <p>如果您没有进行相关操作，请忽略此邮件。</p>
                </div>
                <div class="footer">
                    <p>此邮件由系统自动发送，请勿回复</p>
                    <p>© 2024 XFP密钥获取系统. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
    }

    /**
     * 生成随机验证码
     * @param int $length 验证码长度
     * @return string
     */
    public static function generateVerificationCode($length = 6) {
        $characters = '0123456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $code;
    }
}
