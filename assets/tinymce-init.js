// ویرایشگر TinyMCE مشترک (۹٫۲۰)
function initTinyMCE(selector, height) {
    if (typeof tinymce === 'undefined') return;
    tinymce.init({
        selector: selector,
        directionality: 'rtl',
        height: height || 350,
        menubar: false,
        plugins: 'lists link image table code fullscreen',
        toolbar: 'undo redo | blocks | bold italic underline | alignright aligncenter alignleft | bullist numlist | link image table | code fullscreen',
        block_formats: 'پاراگراف=p;تیتر ۲=h2;تیتر ۳=h3',
        content_style: 'body{font-family:Vazirmatn,Tahoma,sans-serif;direction:rtl;font-size:15px;line-height:2}',
        branding: false,
        promotion: false,
    });
}