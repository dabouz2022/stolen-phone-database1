# Simple Manual Fix for .git Vulnerability

Since the automated script broke your website, here's a simple manual fix that won't break anything.

## **Step 1: SSH into your server**
```bash
ssh root@your-server-ip
```

## **Step 2: Find your nginx configuration file**
```bash
# Check which nginx config file is being used
nginx -T | grep "server {" | head -1
```

Common locations:
- `/etc/nginx/sites-available/default`
- `/etc/nginx/nginx.conf`
- `/etc/nginx/conf.d/default.conf`

## **Step 3: Backup your current config**
```bash
# Replace CONFIG_FILE with your actual config file path
cp CONFIG_FILE CONFIG_FILE.backup
```

## **Step 4: Add security rules manually**

Edit your nginx config file:
```bash
nano CONFIG_FILE
```

**Find the line that says `server {` and add these lines RIGHT AFTER it:**

```nginx
    # Security: Block access to sensitive files and directories
    location ~ /\. {
        deny all;
        return 403;
    }

    # Block access to git repository
    location ~* /\.git {
        deny all;
        return 403;
    }
```

**Example of how it should look:**
```nginx
server {
    listen 80;
    server_name _;

    # Security: Block access to sensitive files and directories
    location ~ /\. {
        deny all;
        return 403;
    }

    # Block access to git repository
    location ~* /\.git {
        deny all;
        return 403;
    }

    location / {
        # your existing location block
    }
}
```

## **Step 5: Test and reload**
```bash
# Test the configuration
nginx -t

# If test passes, reload nginx
systemctl reload nginx
```

## **Step 6: Test the fix**
```bash
# Test .git access (should return 403)
curl -I http://localhost/.git/config

# Test your website still works
curl -I http://localhost/
```

## **If something goes wrong:**
```bash
# Restore backup
cp CONFIG_FILE.backup CONFIG_FILE
systemctl reload nginx
```

## **Alternative: Use the safe script**
If you want to try the safer automated script:
```bash
# Download the safe script
wget https://raw.githubusercontent.com/dabouz2022/stolen-phone-database1/main/safe-git-fix.sh
chmod +x safe-git-fix.sh
sudo ./safe-git-fix.sh
```

This manual fix only adds the essential security rules and won't break your existing configuration.
