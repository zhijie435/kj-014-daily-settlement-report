<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## CRM 客户跟进系统 - 日结算报表模块

### 目录

- [系统要求](#系统要求)
- [环境变量配置](#环境变量配置)
- [部署步骤](#部署步骤)
- [数据库迁移与种子数据](#数据库迁移与种子数据)
- [队列任务配置](#队列任务配置)
- [定时任务](#定时任务)
- [验收命令](#验收命令)
- [API 接口列表](#api-接口列表)
- [常见问题](#常见问题)

---

## 系统要求

| 组件 | 版本要求 |
|------|---------|
| PHP | >= 8.3 |
| Laravel | >= 13.8 |
| 数据库 | MySQL 5.7+ / PostgreSQL 10+ / SQLite 3 |
| PHP 扩展 | BCMath, Ctype, Fileinfo, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML |
| Composer | >= 2.0 |
| Node.js | >= 18.x |
| NPM | >= 9.x |

---

## 环境变量配置

复制 `.env.example` 为 `.env` 并配置以下关键变量：

```bash
cp .env.example .env
```

### 1. 基础应用配置

| 变量名 | 说明 | 示例值 | 必填 |
|--------|------|--------|------|
| `APP_NAME` | 应用名称 | `CRM客户跟进系统` | ✅ |
| `APP_ENV` | 运行环境 | `local` / `production` / `staging` | ✅ |
| `APP_KEY` | 应用加密密钥 | 执行 `php artisan key:generate` 生成 | ✅ |
| `APP_DEBUG` | 调试模式 | `true`(开发) / `false`(生产) | ✅ |
| `APP_URL` | 应用URL | `https://crm.example.com` | ✅ |
| `APP_LOCALE` | 应用语言 | `zh_CN` | 建议 |
| `APP_FALLBACK_LOCALE` | 回退语言 | `zh_CN` | 建议 |

### 2. 数据库配置

#### SQLite (开发/测试环境)

| 变量名 | 说明 | 示例值 | 必填 |
|--------|------|--------|------|
| `DB_CONNECTION` | 数据库驱动 | `sqlite` | ✅ |
| `DB_DATABASE` | 数据库文件路径 | `/var/www/html/database/database.sqlite` | ✅ |

#### MySQL (生产环境推荐)

| 变量名 | 说明 | 示例值 | 必填 |
|--------|------|--------|------|
| `DB_CONNECTION` | 数据库驱动 | `mysql` | ✅ |
| `DB_HOST` | 数据库主机 | `127.0.0.1` | ✅ |
| `DB_PORT` | 数据库端口 | `3306` | ✅ |
| `DB_DATABASE` | 数据库名称 | `crm_system` | ✅ |
| `DB_USERNAME` | 数据库用户名 | `crm_user` | ✅ |
| `DB_PASSWORD` | 数据库密码 | `your_password` | ✅ |
| `DB_CHARSET` | 字符集 | `utf8mb4` | 建议 |
| `DB_COLLATION` | 排序规则 | `utf8mb4_unicode_ci` | 建议 |

### 3. 队列配置

日结算报表使用数据库队列驱动（默认），如需提升性能可切换为 Redis：

| 变量名 | 说明 | 示例值 | 必填 |
|--------|------|--------|------|
| `QUEUE_CONNECTION` | 队列驱动 | `database` 或 `redis` | ✅ |
| `DB_QUEUE_TABLE` | 队列表名 | `jobs` | database驱动时 |
| `DB_QUEUE_RETRY_AFTER` | 重试间隔(秒) | `90` | database驱动时 |

#### Redis 队列配置（生产推荐）

| 变量名 | 说明 | 示例值 | 必填 |
|--------|------|--------|------|
| `REDIS_CLIENT` | Redis客户端 | `phpredis` | ✅ |
| `REDIS_HOST` | Redis主机 | `127.0.0.1` | ✅ |
| `REDIS_PASSWORD` | Redis密码 | `null` 或密码 | 按需 |
| `REDIS_PORT` | Redis端口 | `6379` | ✅ |
| `REDIS_QUEUE` | Redis队列名 | `default` | ✅ |
| `REDIS_QUEUE_RETRY_AFTER` | 重试间隔(秒) | `90` | ✅ |

### 4. 缓存配置

| 变量名 | 说明 | 示例值 | 必填 |
|--------|------|--------|------|
| `CACHE_STORE` | 缓存驱动 | `database` / `redis` / `file` | ✅ |
| `CACHE_PREFIX` | 缓存前缀 | `crm_` | 建议 |

### 5. 会话配置

| 变量名 | 说明 | 示例值 | 必填 |
|--------|------|--------|------|
| `SESSION_DRIVER` | 会话驱动 | `database` | ✅ |
| `SESSION_LIFETIME` | 会话有效期(分钟) | `120` | 建议 |
| `SESSION_DOMAIN` | Cookie域 | `.example.com` | 子域名部署时 |

### 6. 日结算报表专用配置

当前版本报表模块的配置均内置在模型常量中，如需调整可在 `.env` 中添加以下自定义变量并修改代码读取：

| 变量名 | 说明 | 默认值 | 位置 |
|--------|------|--------|------|
| `REPORT_MAX_GENERATE_DAYS` | 批量生成最大天数 | `90` | `DailySettlementReport::MAX_GENERATE_DAYS` |
| `REPORT_CSV_EXPORT_ENCODING` | CSV导出编码 | `UTF-8 BOM` | 导出方法内 |

---

## 部署步骤

### 一键部署（推荐开发环境）

```bash
composer run setup
```

### 手动部署（生产环境）

#### 步骤1: 安装 PHP 依赖

```bash
cd /path/to/project/backend
composer install --optimize-autoloader --no-dev
```

#### 步骤2: 配置环境变量

```bash
cp .env.example .env
vim .env
# 按照【环境变量配置】章节配置
php artisan key:generate
```

#### 步骤3: 安装前端依赖并构建

```bash
npm install --omit=dev
npm run build
```

#### 步骤4: 数据库迁移与种子数据

详见 [数据库迁移与种子数据](#数据库迁移与种子数据) 章节。

#### 步骤5: 目录权限

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

#### 步骤6: 启动队列工作进程

详见 [队列任务配置](#队列任务配置) 章节。

#### 步骤7: 配置定时任务

详见 [定时任务](#定时任务) 章节。

#### 步骤8: 配置 Web 服务器（Nginx 示例）

```nginx
server {
    listen 80;
    server_name crm.example.com;
    root /var/www/html/backend/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 数据库迁移与种子数据

### 迁移文件列表

日结算报表模块相关迁移（按执行顺序）：

| 迁移文件 | 说明 |
|---------|------|
| [0001_01_01_000000_create_users_table.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/.trae-lane-targets/014-日结算报表/backend/database/migrations/0001_01_01_000000_create_users_table.php) | 用户表 |
| [0001_01_01_000001_create_cache_table.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/.trae-lane-targets/014-日结算报表/backend/database/migrations/0001_01_01_000001_create_cache_table.php) | 缓存表 |
| [0001_01_01_000002_create_jobs_table.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/.trae-lane-targets/014-日结算报表/backend/database/migrations/0001_01_01_000002_create_jobs_table.php) | 队列表 |
| [2024_01_01_000012_create_suppliers_table.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/.trae-lane-targets/014-日结算报表/backend/database/migrations/2024_01_01_000012_create_suppliers_table.php) | 供应商表 |
| [2024_01_01_000013_create_distributors_table.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/.trae-lane-targets/014-日结算报表/backend/database/migrations/2024_01_01_000013_create_distributors_table.php) | 分销商表 |
| [2024_01_01_000014_create_categories_table.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/.trae-lane-targets/014-日结算报表/backend/database/migrations/2024_01_01_000014_create_categories_table.php) | 分类表 |
| [2024_01_01_000015_create_products_table.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/.trae-lane-targets/014-日结算报表/backend/database/migrations/2024_01_01_000015_create_products_table.php) | 商品表 |
| [2024_01_01_000016_create_inventory_table.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/.trae-lane-targets/014-日结算报表/backend/database/migrations/2024_01_01_000016_create_inventory_table.php) | 库存表 |
| [2024_01_01_000017_create_orders_table.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/.trae-lane-targets/014-日结算报表/backend/database/migrations/2024_01_01_000017_create_orders_table.php) | 订单表 |
| [2024_01_01_000018_create_order_items_table.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/.trae-lane-targets/014-日结算报表/backend/database/migrations/2024_01_01_000018_create_order_items_table.php) | 订单明细表 |
| [2024_01_01_000019_create_payments_table.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/.trae-lane-targets/014-日结算报表/backend/database/migrations/2024_01_01_000019_create_payments_table.php) | 支付表 |
| [2024_01_01_000020_add_role_to_users_table.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/.trae-lane-targets/014-日结算报表/backend/database/migrations/2024_01_01_000020_add_role_to_users_table.php) | 用户角色 |
| [2026_06_20_162022_create_personal_access_tokens_table.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/.trae-lane-targets/014-日结算报表/backend/database/migrations/2026_06_20_162022_create_personal_access_tokens_table.php) | API Token表 |
| [2026_06_20_162023_create_permission_tables.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/.trae-lane-targets/014-日结算报表/backend/database/migrations/2026_06_20_162023_create_permission_tables.php) | 权限表 |
| [2026_06_21_000001_create_daily_settlement_reports_table.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/.trae-lane-targets/014-日结算报表/backend/database/migrations/2026_06_21_000001_create_daily_settlement_reports_table.php) | **日结算报表主表** |
| [2026_06_21_000002_fix_daily_settlement_reports_unique_index.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/.trae-lane-targets/014-日结算报表/backend/database/migrations/2026_06_21_000002_fix_daily_settlement_reports_unique_index.php) | **修复报表唯一索引** |
| [2026_06_21_000003_add_status_to_daily_settlement_reports.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/.trae-lane-targets/014-日结算报表/backend/database/migrations/2026_06_21_000003_add_status_to_daily_settlement_reports.php) | **报表状态和审核字段** |

### 执行迁移

```bash
# 查看待执行的迁移
php artisan migrate:status

# 执行所有未执行的迁移
php artisan migrate --force

# 首次部署（会删除所有表后重建，仅开发环境使用！）
php artisan migrate:fresh --seed
```

### 种子数据

#### 1. 权限和默认用户种子

[PermissionSeeder.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/.trae-lane-targets/014-日结算报表/backend/database/seeders/PermissionSeeder.php) 包含：

- 角色：`platform`（平台管理员）、`supplier`（供应商）、`distributor`（分销商）、`regional_agent`（区域代理）
- 日结算报表权限：
  - `report.view` - 查看报表
  - `report.generate` - 生成报表
  - `report.regenerate` - 重新生成报表
  - `report.manage` - 管理报表（编辑、退回草稿）
  - `report.confirm` - 确认报表
  - `report.audit` - 审核报表
  - `report.lock` - 锁定报表
  - `report.export` - 导出报表
  - `report.delete` - 删除报表
- 默认管理员账号：`admin@shearerline.com` / `password123`

#### 2. 执行种子数据

```bash
# 执行指定的 Seeder
php artisan db:seed --class=PermissionSeeder

# 或执行 DatabaseSeeder（需要先配置 DatabaseSeeder 调用 PermissionSeeder）
php artisan db:seed --force
```

#### 3. 修改 DatabaseSeeder 自动执行权限种子

编辑 [DatabaseSeeder.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/.trae-lane-targets/014-日结算报表/backend/database/seeders/DatabaseSeeder.php)：

```php
public function run(): void
{
    $this->call(PermissionSeeder::class);
}
```

---

## 队列任务配置

日结算报表模块使用 Laravel 队列系统处理报表生成等异步任务。

### 队列连接配置

推荐生产环境使用 Redis 队列驱动：

```env
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_QUEUE=default
REDIS_QUEUE_RETRY_AFTER=90
```

开发环境可使用 database 驱动（已默认）：

```env
QUEUE_CONNECTION=database
DB_QUEUE_TABLE=jobs
DB_QUEUE_RETRY_AFTER=90
```

### 启动队列工作进程

#### 方式1: 直接启动（开发环境）

```bash
# 监听所有队列（开发环境）
php artisan queue:listen --tries=3

# 或使用 work 命令（生产性能更好）
php artisan queue:work --tries=3 --timeout=600
```

#### 方式2: Supervisor 守护进程（生产环境推荐）

安装 Supervisor：

```bash
# Ubuntu / Debian
sudo apt-get install supervisor

# CentOS / RHEL
sudo yum install supervisor
```

创建配置文件 `/etc/supervisor/conf.d/crm-queue.conf`：

```ini
[program:crm-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/backend/artisan queue:work redis --sleep=3 --tries=3 --timeout=600 --queue=default
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/html/backend/storage/logs/queue-worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
```

启动并管理：

```bash
# 重新加载配置并启动
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start crm-queue:*

# 查看状态
sudo supervisorctl status

# 重启
sudo supervisorctl restart crm-queue:*

# 停止
sudo supervisorctl stop crm-queue:*
```

#### 方式3: systemd 服务（生产环境替代方案）

创建服务文件 `/etc/systemd/system/crm-queue.service`：

```ini
[Unit]
Description=CRM Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html/backend
ExecStart=/usr/bin/php artisan queue:work --tries=3 --timeout=600
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

启动服务：

```bash
sudo systemctl daemon-reload
sudo systemctl enable crm-queue
sudo systemctl start crm-queue
sudo systemctl status crm-queue
```

### 失败任务处理

```bash
# 查看失败的队列任务
php artisan queue:failed

# 重试所有失败任务
php artisan queue:retry all

# 重试指定ID的任务
php artisan queue:retry <id>

# 删除指定失败任务
php artisan queue:forget <id>

# 清空所有失败任务
php artisan queue:flush
```

### 队列管理命令速查

```bash
# 查看队列大小
php artisan queue:monitor default

# 重启所有队列工作进程（修改代码后需执行）
php artisan queue:restart
```

---

## 定时任务

### 配置 Cron 任务

在服务器 crontab 中添加：

```bash
* * * * * cd /path/to/project/backend && php artisan schedule:run >> /dev/null 2>&1
```

### 建议添加的日结算报表定时任务

编辑 [routes/console.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/.trae-lane-targets/014-日结算报表/backend/routes/console.php) 添加以下调度：

```php
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    $yesterday = now()->subDay()->format('Y-m-d');
    \App\Models\DailySettlementReport::generateForDate(
        $yesterday,
        \App\Models\DailySettlementReport::TYPE_ALL,
        1
    );
})->dailyAt('02:00')->name('auto-generate-daily-report');

Schedule::command('queue:restart')->dailyAt('03:00');
```

---

## 验收命令

### 1. 环境检查

```bash
# 检查 PHP 版本
php -v

# 检查 Laravel 版本
php artisan --version

# 检查扩展
php -m | grep -E "(pdo|mbstring|xml|openssl|json|fileinfo|ctype|tokenizer|bcmath)"

# 检查 Composer 依赖安装状态
composer install --dry-run

# 检查环境配置状态
php artisan about
```

### 2. 配置和缓存命令

```bash
# 清除所有缓存
php artisan optimize:clear

# 生产环境优化配置缓存
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 一键优化
php artisan optimize
```

### 3. 数据库验证

```bash
# 检查迁移状态
php artisan migrate:status

# 验证数据库连接
php artisan tinker --execute="echo DB::connection()->getDatabaseName();"

# 检查表是否存在（日结算报表相关）
php artisan tinker --execute="
echo 'daily_settlement_reports: ' . (Schema::hasTable('daily_settlement_reports') ? 'OK' : 'MISSING') . PHP_EOL;
echo 'jobs: ' . (Schema::hasTable('jobs') ? 'OK' : 'MISSING') . PHP_EOL;
echo 'failed_jobs: ' . (Schema::hasTable('failed_jobs') ? 'OK' : 'MISSING') . PHP_EOL;
echo 'permissions: ' . (Schema::hasTable('permissions') ? 'OK' : 'MISSING') . PHP_EOL;
echo 'roles: ' . (Schema::hasTable('roles') ? 'OK' : 'MISSING') . PHP_EOL;
"

# 验证权限数据
php artisan tinker --execute="
echo '平台管理员角色: ' . (\Spatie\Permission\Models\Role::where('name', 'platform')->first() ? 'OK' : 'MISSING') . PHP_EOL;
echo 'report.view 权限: ' . (\Spatie\Permission\Models\Permission::where('name', 'report.view')->first() ? 'OK' : 'MISSING') . PHP_EOL;
echo '默认管理员: ' . (\App\Models\User::where('email', 'admin@shearerline.com')->first() ? 'OK' : 'MISSING') . PHP_EOL;
"
```

### 4. 路由检查

```bash
# 列出所有 API 路由（过滤日结算报表相关）
php artisan route:list --path=daily-settlement

# 列出所有权限相关中间件路由
php artisan route:list --middleware=auth:sanctum
```

### 5. 单元测试与功能测试

```bash
# 执行所有测试
php artisan test

# 执行日结算报表相关测试
php artisan test --filter=DailySettlement

# 执行单元测试（Service / Model）
php artisan test tests/Unit/DailySettlementReportServiceTest.php
php artisan test tests/Unit/DailySettlementReportModelTest.php

# 执行功能测试（Controller）
php artisan test tests/Feature/DailySettlementReportControllerTest.php

# 测试覆盖率（需安装 Xdebug/PCOV）
php artisan test --coverage
```

### 6. API 接口验收

以下命令使用 curl 或 HTTPie 进行接口验证：

#### 6.1 获取登录 Token

```bash
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@shearerline.com",
    "password": "password123"
  }'
```

保存返回的 token 为环境变量：

```bash
export TOKEN="your_token_here"
```

#### 6.2 生成日报表

```bash
curl -X POST http://localhost/api/daily-settlement-reports \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "report_date": "2024-06-20",
    "type": "all",
    "remark": "验收测试"
  }'
```

#### 6.3 批量生成日报表

```bash
curl -X POST http://localhost/api/daily-settlement-reports/generate-batch \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "date_from": "2024-06-15",
    "date_to": "2024-06-20",
    "type": "all"
  }'
```

#### 6.4 查询报表列表

```bash
curl "http://localhost/api/daily-settlement-reports?date_from=2024-06-01&date_to=2024-06-30&type=all&per_page=10" \
  -H "Authorization: Bearer $TOKEN"
```

#### 6.5 查询报表汇总

```bash
curl "http://localhost/api/daily-settlement-reports/summary?date_from=2024-06-01&date_to=2024-06-30" \
  -H "Authorization: Bearer $TOKEN"
```

#### 6.6 报表全流程状态流转

```bash
# 假设报表 ID=1
export REPORT_ID=1

# 确认报表
curl -X POST http://localhost/api/daily-settlement-reports/$REPORT_ID/confirm \
  -H "Authorization: Bearer $TOKEN"

# 审核报表
curl -X POST http://localhost/api/daily-settlement-reports/$REPORT_ID/audit \
  -H "Authorization: Bearer $TOKEN"

# 锁定报表
curl -X POST http://localhost/api/daily-settlement-reports/$REPORT_ID/lock \
  -H "Authorization: Bearer $TOKEN"
```

#### 6.7 导出 CSV

```bash
curl -o report.csv "http://localhost/api/daily-settlement-reports/export?date_from=2024-06-01&date_to=2024-06-30&format=csv" \
  -H "Authorization: Bearer $TOKEN"
```

### 7. 队列验收

```bash
# 检查队列工作进程是否运行
ps aux | grep "queue:work" || ps aux | grep "queue:listen"

# 清空测试队列
php artisan queue:clear

# 向队列推入测试任务
php artisan tinker --execute="
\Queue::push(function() {
    \Log::info('队列验收测试任务执行成功');
});
echo '测试任务已入队';
"

# 检查队列日志
tail -50 storage/logs/queue-worker.log
tail -50 storage/logs/laravel.log
```

### 8. 综合验收脚本

创建 `scripts/acceptance.sh`：

```bash
#!/bin/bash
set -e

echo "========================================="
echo "  CRM日结算报表 - 部署验收脚本"
echo "========================================="
echo ""

echo "[1/8] 检查 PHP 版本..."
php -v | head -1

echo ""
echo "[2/8] 检查 Laravel 版本..."
php artisan --version

echo ""
echo "[3/8] 检查环境配置..."
php artisan about --only=environment

echo ""
echo "[4/8] 检查数据库连接..."
php artisan tinker --execute="echo '连接成功: ' . DB::connection()->getDatabaseName() . PHP_EOL;"

echo ""
echo "[5/8] 检查迁移状态..."
php artisan migrate:status

echo ""
echo "[6/8] 运行单元测试..."
php artisan test tests/Unit/DailySettlementReportServiceTest.php tests/Unit/DailySettlementReportModelTest.php --no-coverage

echo ""
echo "[7/8] 运行功能测试..."
php artisan test tests/Feature/DailySettlementReportControllerTest.php --no-coverage

echo ""
echo "[8/8] 检查队列工作进程..."
if ps aux | grep -q "[q]ueue:work\|[q]ueue:listen"; then
    echo "✅ 队列工作进程运行中"
else
    echo "⚠️  队列工作进程未运行（生产环境需要启动）"
fi

echo ""
echo "========================================="
echo "  验收完成！"
echo "========================================="
```

执行验收脚本：

```bash
chmod +x scripts/acceptance.sh
./scripts/acceptance.sh
```

---

## API 接口列表

所有接口前缀：`/api`

### 认证接口

| 方法 | 路由 | 说明 | 权限 |
|------|------|------|------|
| POST | `/auth/login` | 登录获取 Token | 公开 |
| POST | `/auth/logout` | 登出 | 已登录 |
| GET | `/auth/me` | 获取当前用户 | 已登录 |

### 日结算报表接口

| 方法 | 路由 | 说明 | 权限 |
|------|------|------|------|
| GET | `/daily-settlement-reports` | 报表列表（分页） | `report.view` |
| GET | `/daily-settlement-reports/{id}` | 报表详情 | `report.view` |
| POST | `/daily-settlement-reports` | 生成单日报表 | `report.generate` |
| POST | `/daily-settlement-reports/generate-batch` | 批量生成报表 | `report.generate` |
| POST | `/daily-settlement-reports/{id}/regenerate` | 重新生成报表 | `report.regenerate` |
| PUT | `/daily-settlement-reports/{id}` | 修改报表备注 | `report.manage` |
| DELETE | `/daily-settlement-reports/{id}` | 删除报表（软删除） | `report.delete` |
| POST | `/daily-settlement-reports/{id}/confirm` | 确认报表 | `report.confirm` |
| POST | `/daily-settlement-reports/{id}/audit` | 审核报表 | `report.audit` |
| POST | `/daily-settlement-reports/{id}/lock` | 锁定报表 | `report.lock` |
| POST | `/daily-settlement-reports/{id}/revert-to-draft` | 退回草稿 | `report.manage` |
| GET | `/daily-settlement-reports/summary` | 数据汇总 | `report.view` |
| GET | `/daily-settlement-reports/export` | 导出 CSV | `report.export` |

#### 报表状态流转图

```
草稿(draft) ──确认──> 已确认(confirmed) ──审核──> 已审核(audited) ──锁定──> 已锁定(locked)
    ^                      |
    |                      |
    └─────退回草稿─────────┘
```

---

## 常见问题

### Q1: 生成报表时提示"日期不能是未来日期"

A: 报表生成仅支持当前日期及以前的日期，请检查 `report_date` 参数。

### Q2: 批量生成时提示"日期范围过大"

A: 默认最大批量生成 90 天，如需调整请修改 `DailySettlementReport::MAX_GENERATE_DAYS` 常量或通过环境变量 `REPORT_MAX_GENERATE_DAYS` 配置。

### Q3: 报表状态流转失败

A: 检查当前报表状态是否允许操作，参考【报表状态流转图】。只有 `draft` 和 `confirmed` 状态可以重新生成或编辑。

### Q4: 队列任务卡住不执行

A: 
1. 检查队列工作进程是否运行：`ps aux | grep queue`
2. 查看失败任务：`php artisan queue:failed`
3. 重启队列：`php artisan queue:restart`
4. 检查 `storage/logs/laravel.log` 错误日志

### Q5: CSV 导出中文乱码

A: 系统默认使用 UTF-8 BOM 编码，如果在 Excel 中打开仍乱码，请手动选择 UTF-8 编码打开。

### Q6: 迁移执行失败"索引已存在"

A: 迁移 `000002_fix_daily_settlement_reports_unique_index.php` 会自动处理索引冲突，如仍有问题请手动删除旧索引后重新迁移：
```bash
php artisan migrate:rollback --step=3
php artisan migrate
```

### Q7: 权限相关 403 错误

A: 请确保已执行 `PermissionSeeder`，并且用户拥有正确的角色和权限：
```bash
php artisan db:seed --class=PermissionSeeder --force
```
