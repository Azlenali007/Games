/**
 * Aura Gaming Platform - Production Bridge Server
 * Starts local MariaDB daemon, spawns PHP built-in web server,
 * proxies all HTTP traffic to PHP, and runs automated cron progression.
 */

import express from 'express';
import http from 'http';
import { spawn, execSync } from 'child_process';
import path from 'path';
import fs from 'fs';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = 3000;
const PHP_PORT = 8088;
const APP_DIR = __dirname;
const PUBLIC_HTML = path.join(APP_DIR, 'public_html');

// 1. Ensure MariaDB is running
function ensureMariaDB() {
  try {
    execSync('mariadb -u root -e "SELECT 1;" > /dev/null 2>&1');
    console.log('[Database] MariaDB is already running.');
  } catch (err) {
    console.log('[Database] Starting MariaDB daemon...');
    try {
      execSync('mkdir -p /var/run/mysqld && chown -R mysql:mysql /var/run/mysqld /var/lib/mysql');
      spawn('mariadbd', ['--user=mysql'], { detached: true, stdio: 'ignore' });
      // Wait up to 5 seconds for socket
      for (let i = 0; i < 10; i++) {
        try {
          execSync('mariadb -u root -e "SELECT 1;" > /dev/null 2>&1');
          console.log('[Database] MariaDB successfully connected.');
          break;
        } catch (e) {
          execSync('sleep 0.5');
        }
      }
    } catch (e) {
      console.error('[Database] MariaDB startup error:', e);
    }
  }

  // Ensure database and tables exist
  try {
    execSync('mariadb -u root -e "CREATE DATABASE IF NOT EXISTS gaming_platform;"');
    const tableCount = parseInt(execSync('mariadb -u root gaming_platform -se "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = \'gaming_platform\';"').toString().trim(), 10);
    if (tableCount === 0) {
      console.log('[Database] Importing database.sql schema and seeder...');
      execSync(`mariadb -u root gaming_platform < ${path.join(PUBLIC_HTML, 'database.sql')}`);
      execSync(`php ${path.join(PUBLIC_HTML, 'seed.php')}`);
    }
  } catch (e) {
    console.error('[Database] Schema setup error:', e);
  }
}

// 2. Start PHP Built-In Server
let phpProcess: any = null;
function startPhpServer() {
  console.log(`[PHP] Starting PHP 8.2 server on 127.0.0.1:${PHP_PORT} serving ${PUBLIC_HTML}...`);
  phpProcess = spawn('php', ['-S', `127.0.0.1:${PHP_PORT}`, '-t', PUBLIC_HTML], {
    stdio: 'inherit'
  });

  phpProcess.on('error', (err: any) => {
    console.error('[PHP] Process error:', err);
  });
  phpProcess.on('exit', (code: any) => {
    console.log('[PHP] Process exited with code', code);
  });
}

// 3. Automated Cron Loop (Progresses rounds every 2.5 seconds)
function startCronLoop() {
  const cronScript = path.join(PUBLIC_HTML, 'cron.php');
  setInterval(() => {
    try {
      execSync(`php ${cronScript} > /dev/null 2>&1`);
    } catch (e) {
      // ignore
    }
  }, 2500);
}

// 4. Reverse Proxy all requests from Port 3000 to PHP on Port 8088
app.use((req, res) => {
  const options: http.RequestOptions = {
    hostname: '127.0.0.1',
    port: PHP_PORT,
    path: req.url,
    method: req.method,
    headers: {
      ...req.headers,
      host: req.headers.host || 'localhost:3000',
      'x-forwarded-for': req.ip || req.connection.remoteAddress || '127.0.0.1',
      'x-forwarded-proto': req.protocol || 'http'
    }
  };

  const proxyReq = http.request(options, (proxyRes) => {
    res.writeHead(proxyRes.statusCode || 200, proxyRes.headers);
    proxyRes.pipe(res);
  });

  proxyReq.on('error', (err) => {
    console.error('[Proxy Error]', err.message);
    if (!res.headersSent) {
      res.status(502).send('<html><body style="background:#07090e;color:#cbd5e1;font-family:sans-serif;padding:2rem;"><h2>502 Gateway Error</h2><p>PHP backend server is initializing. Please refresh in a moment.</p></body></html>');
    }
  });

  req.pipe(proxyReq);
});

// Initialize system
ensureMariaDB();
startPhpServer();
startCronLoop();

app.listen(PORT, '0.0.0.0', () => {
  console.log(`[Aura Platform] Bridge Server listening on http://0.0.0.0:${PORT} -> Forwarding to PHP 8.2 + MariaDB`);
});
