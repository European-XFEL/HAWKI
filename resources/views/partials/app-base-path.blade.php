<script>
    window.appBasePath = @json(app_url_path());
    window.appUrl = function (path = '') {
        const suffix = String(path).replace(/^\/+/, '');

        return suffix
            ? `${window.appBasePath.replace(/\/$/, '')}/${suffix}`
            : window.appBasePath;
    };
</script>
