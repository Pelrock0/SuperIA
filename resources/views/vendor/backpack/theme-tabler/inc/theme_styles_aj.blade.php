{{-- 
    we use a render blocking script in <head> to force the theme attribute to be in the document before it renders 
    avoiding white flicks when for example, using the dark color mode.
--}}
<script>
document.documentElement.setAttribute("data-bs-theme", "light");
/* Force sidebar to dark theme for Superia teal design */
document.addEventListener("DOMContentLoaded", function() {
    var aside = document.querySelector("aside.navbar");
    if (aside) aside.setAttribute("data-menu-theme", "dark");
});
</script>

@basset('https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta19/dist/css/tabler.min.css')

    <link rel="stylesheet" type="text/css" href="/css/style_aj.css?v=stitch-20260412b">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
