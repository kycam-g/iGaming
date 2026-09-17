# XAMPP VirtualHost

Use o VirtualHost apontando para `C:/xampp/htdocs/mz90/public`.

```apache
<VirtualHost *:80>
    ServerName mz90.local
    DocumentRoot "C:/xampp/htdocs/mz90/public"

    <Directory "C:/xampp/htdocs/mz90/public">
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

No arquivo `hosts`:

```text
127.0.0.1 mz90.local
```

No `.env`:

```env
APP_URL=http://mz90.local
APP_BASE_PATH=
```

Os assets públicos ficam em `public/assets/`.
