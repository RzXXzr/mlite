# Routing QR Code - surat.klinikarrohman.com

## Perubahan yang Sudah Dilakukan

### 1. Update URL QR Code
- **Surat Sehat:** `https://surat.klinikarrohman.com/surat/verifikasi-sehat/{token}`
- **Surat Sakit:** `https://surat.klinikarrohman.com/surat/verifikasi-sakit/{token}`

### 2. Logo di Tengah QR Code
- QR code sekarang menampilkan logo klinik di tengah (30x30px)
- Logo diambil dari `{$settings.logo}`
- Background putih dengan border-radius dan shadow

## Konfigurasi Routing yang Perlu Dilakukan

### Nginx Configuration
Tambahkan virtual host untuk `surat.klinikarrohman.com`:

```nginx
server {
    listen 80;
    server_name surat.klinikarrohman.com;
    
    # Jika menggunakan SSL
    # listen 443 ssl http2;
    # ssl_certificate /path/to/cert.pem;
    # ssl_certificate_key /path/to/key.pem;
    
    # Root directory sama dengan mlite
    root /home/slemp/wwwroot/mlite;
    index index.php index.html;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/tmp/php-cgi-82.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### Apache Configuration (.htaccess atau VirtualHost)

```apache
<VirtualHost *:80>
    ServerName surat.klinikarrohman.com
    DocumentRoot /home/slemp/wwwroot/mlite
    
    <Directory /home/slemp/wwwroot/mlite>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### DNS Configuration
Tambahkan A Record:
```
surat.klinikarrohman.com  →  IP Server (10.10.10.58)
```

## Testing
Setelah routing dikonfigurasi, test dengan:
1. Generate surat baru (sakit atau sehat)
2. Scan QR code
3. URL harus mengarah ke `https://surat.klinikarrohman.com/surat/verifikasi-{type}/{token}`

## File yang Diupdate
- `/home/slemp/wwwroot/mlite/plugins/surat/view/admin/surat.sehat.html`
- `/home/slemp/wwwroot/mlite/plugins/surat/view/admin/surat.sakit.html`

## Catatan
- Endpoint verifikasi di backend (`plugins/surat/Site.php`) tetap sama
- Tidak ada perubahan di backend, hanya frontend QR generation
- Logo overlay menggunakan CSS positioning (tidak memerlukan library tambahan)
