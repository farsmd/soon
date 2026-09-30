<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{seo_title}}</title>
    <meta name="description" content="{{seo_description}}">
    <link rel="stylesheet" href="style.css">
    <script>if(localStorage.getItem('cms-theme')==='dark'){document.documentElement.dataset.theme='dark';}</script>
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="index.php">{{site_title}}</a>
        {{menu}}
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
        <p>در این قالب هم می‌توانید از کد PHP و پلیس‌هولدرهای <code>{{site_title}}</code> و <code>{{site_description}}</code> و <code>{{current_year}}</code> و <code>{{menu}}</code> استفاده کنید.</p>
    </div>
</section>

<footer class="site-footer">
    <div class="container">
        <p>© {{current_year}} {{site_title}}</p>
    </div>
</footer>
<script>
(function(){var b=document.getElementById('theme-toggle');if(!b)return;b.addEventListener('click',function(){var d=document.documentElement.dataset.theme==='dark';document.documentElement.dataset.theme=d?'light':'dark';localStorage.setItem('cms-theme',d?'light':'dark');});})();
</script>
</body>
</html>
