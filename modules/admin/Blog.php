<?php
// Shim سازگاری — کد واقعی به modules/blog/admin.php منتقل شده (۹٫۹۹٫۱۳)
declare(strict_types=1);
if (!defined('CMS_ADMIN_PANEL')) { http_response_code(403); exit; }
require_once __DIR__ . '/../blog/admin.php';
