# Simple Manual Fix for .git Vulnerability (Version 2)

Since the automated script had issues, here's a simple manual approach that will definitely work.

## **Step 1: Check your current nginx configuration**
```bash
cat /etc/nginx/nginx.conf
```

## **Step 2: Find where your server block is**
Look for lines that contain `server {` in your nginx configuration.

## **Step 3: Manual Fix Approach**

### **Option A: If server block is in main nginx.conf**
```bash
# Backup
cp /etc/nginx/nginx.conf /etc/nginx/nginx.conf.backup

# Edit the file
nano /etc/nginx/nginx.conf
```

### **Option B: If server block is in included file**
```bash
# Find included files
grep "include" /etc/nginx/nginx.conf

# Usually it's in one of these:
nano /etc/nginx/sites-available/default
# or
nano /etc/nginx/conf.d/default.conf
```

## **Step 4: Add security rules manually**

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

## **Step 7: Remove .git directory if it exists**
```bash
# Check if .git exists in web root
ls -la /var/www/html/

# If .git exists, remove it
rm -rf /var/www/html/.git
```

## **If something goes wrong:**
```bash
# Restore backup
cp /etc/nginx/nginx.conf.backup /etc/nginx/nginx.conf
systemctl reload nginx
```

## **Quick Diagnostic Commands:**

```bash
# Check nginx status
systemctl status nginx

# Check nginx error logs
tail -f /var/log/nginx/error.log

# Check if port 80 is listening
netstat -tlnp | grep :80

# Test .git access
curl -I http://localhost/.git/config
```

This manual approach is the most reliable way to fix the vulnerability without breaking your website.
