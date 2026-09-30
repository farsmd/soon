<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{site_title}}</title>
    <meta name="description" content="{{site_description}}">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="index.php">{{site_title}}</a>
        <nav class="main-nav">
            <a href="admin.php">مدیریت</a>
        </nav>
    </div>
</header>

<section class="slider">
    <div class="container">
        <h1>{{site_title}}</h1>
        <p class="lead">{{site_description}}</p>
    </div>
</section>

<section class="content-section">
    <div class="container">
        <h2>صفحه تک‌قالبی</h2>
        <p>این فایل <code>template_single.php</code> یک قالب کامل و مستقل است: هدر، بدنه و فوتر همه در همین یک فایل قرار دارند. برای استفاده از آن، در پنل مدیریت همه بخش‌ها را غیرفعال کنید و فقط یک بخش با این قالب فعال بگذارید.</p>
        <p>در این قالب هم می‌توانید از کد PHP و پلیس‌هولدرهای <code>{{site_title}}</code> و <code>{{site_description}}</code> و <code>{{current_year}}</code> استفاده کنید.</p>
    </div>
</section>

<footer class="site-footer">
    <div class="container">
        <p>© {{current_year}} {{site_title}}</p>
    </div>
</footer>
</body>
</html>
