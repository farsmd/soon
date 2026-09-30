<section class="slider">
    <div class="container">
        <h1>{{site_title}}</h1>
        <p class="lead">{{site_description}}</p>
        <?php // نمونه کد PHP داخل قالب: متغیرهای $site_title و $site_description در دسترس‌اند ?>
        <p class="slider-note">امروز: <?= e(date('Y/m/d')) ?> — خوش آمدید به <?= e($site_title) ?></p>
        <a class="btn btn-light" href="#content">شروع کنید</a>
    </div>
</section>
