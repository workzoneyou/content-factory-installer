(function ($) {
    // Функция для инъекции стилей
    function injectStyles(id, css) {
        var $style = $('#' + id);
        if (!$style.length) {
            $style = $('<style id="' + id + '"></style>').appendTo('head');
        }
        $style.text(css);
    }

    // Определение режима (Статья или Список)
    function isArticleMode() {
        var body = $('body');
        // Если это главная или блог — всегда режим списка
        if (body.hasClass('home') || body.hasClass('blog')) return false;

        return window.firsanov_is_singular === true ||
            body.hasClass('single') ||
            body.hasClass('singular') ||
            body.hasClass('page') ||
            $('article.single-post').length > 0;
    }

    // Мгновенное обновление Цвета бренда
    wp.customize('brand_color', function (value) {
        value.bind(function (newval) {
            injectStyles('firsanov-preview-brand', ':root { --primary: ' + newval + ' !important; }');
        });
    });

    // Мгновенное обновление Цвета фона (Коридора)
    wp.customize('bg_color', function (value) {
        value.bind(function (newval) {
            var isArticle = isArticleMode();

            // Парсинг цвета
            var hex = newval.replace('#', '');
            if (hex.length === 3) hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
            var r = parseInt(hex.substr(0, 2), 16) || 255;
            var g = parseInt(hex.substr(2, 2), 16) || 255;
            var b = parseInt(hex.substr(4, 2), 16) || 255;

            var brightness = ((r * 299) + (g * 587) + (b * 114)) / 1000;
            var textColor = (brightness > 128) ? '#000000' : '#ffffff';

            var css = '';
            if (isArticle) {
                // РЕЖИМ СТАТЬИ: Поля — БЕЛЫЕ, Статья — ЦВЕТНАЯ
                css += ':root { --body-bg: #ffffff !important; --post-bg: ' + newval + ' !important; --bg-color: ' + newval + ' !important; --text-color: ' + textColor + ' !important; }';
                css += 'body { background: #ffffff !important; }';
                css += 'article.single-post, article.page, .article-inner { background: ' + newval + ' !important; }';
            } else {
                // РЕЖИМ СПИСКА: Поля — ЦВЕТНЫЕ, Карточки — БЕЛЫЕ
                css += ':root { --body-bg: ' + newval + ' !important; --post-bg: #ffffff !important; --bg-color: ' + newval + ' !important; --text-color: ' + textColor + ' !important; }';
                css += 'body { background: ' + newval + ' !important; }';
                css += 'article.post-card, article.post, .type-post { background: #ffffff !important; }';
            }
            injectStyles('firsanov-preview-bg', css);
        });
    });
})(jQuery);
