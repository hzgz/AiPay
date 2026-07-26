<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

use app\support\LegacyPassword;

require_once dirname(__DIR__, 2) . '/app/support/LegacyPassword.php';

final class AiPayInstallSupport
{
    private function __construct()
    {
    }

    private static function businessTable(string $name): string
    {
        return 'aip' . 'ay_' . $name;
    }

    public static function requireCli(): void
    {
        if (PHP_SAPI !== 'cli') {
            fwrite(STDERR, "[FAIL] This script must be run from CLI.\n");
            exit(1);
        }
    }

    public static function createPdo(string $projectRoot): PDO
    {
        self::loadEnvFile($projectRoot);
        $config = require $projectRoot . '/config/database.php';
        $connection = $config['connections'][$config['default']] ?? null;
        if (!is_array($connection)) {
            throw new RuntimeException('database configuration is invalid');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            (string)($connection['host'] ?? '127.0.0.1'),
            (string)($connection['port'] ?? '3306'),
            (string)($connection['database'] ?? ''),
            (string)($connection['charset'] ?? 'utf8')
        );

        return new PDO($dsn, (string)($connection['username'] ?? ''), (string)($connection['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public static function loadEnvFile(string $projectRoot): void
    {
        $envPath = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($envPath)) {
            return;
        }

        if (class_exists('Dotenv\\Dotenv')) {
            if (method_exists('Dotenv\\Dotenv', 'createUnsafeImmutable')) {
                \Dotenv\Dotenv::createUnsafeImmutable($projectRoot)->safeLoad();
                return;
            }

            \Dotenv\Dotenv::createMutable($projectRoot)->safeLoad();
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === '') {
                continue;
            }

            $length = strlen($value);
            if ($length >= 2) {
                $first = $value[0];
                $last = $value[$length - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            putenv(sprintf('%s=%s', $key, $value));
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    public static function generatePassword(int $length = 20): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*_-+=';
        $password = '';
        $max = strlen($alphabet) - 1;

        for ($index = 0; $index < $length; $index++) {
            $password .= $alphabet[random_int(0, $max)];
        }

        return $password;
    }

    public static function generateSecret(int $bytes = 24): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * @return array{id:int,username:string,password:string,nickname:string,created:bool,updated:bool}
     */
    public static function ensureAdmin(PDO $pdo, array $payload): array
    {
        $username = trim((string)($payload['username'] ?? ''));
        $password = trim((string)($payload['password'] ?? ''));
        $nickname = trim((string)($payload['nickname'] ?? ''));

        if ($username === '') {
            throw new InvalidArgumentException('admin username is required');
        }

        if ($password === '') {
            throw new InvalidArgumentException('admin password is required');
        }

        if ($nickname === '') {
            $nickname = 'AiPayAdmin';
        }

        self::assertTableExists($pdo, 'admin_admin');
        self::assertTableExists($pdo, 'admin_admin_role');

        $duplicateId = self::fetchValue(
            $pdo,
            'SELECT id FROM admin_admin WHERE username = :username AND id <> 1 LIMIT 1',
            [':username' => $username]
        );
        if ($duplicateId !== null) {
            throw new RuntimeException(sprintf(
                'admin username "%s" already exists on id=%d, please choose another username or clean the database first',
                $username,
                (int)$duplicateId
            ));
        }

        $existing = self::fetchRow($pdo, 'SELECT id, create_time FROM admin_admin WHERE id = 1 LIMIT 1');
        $now = self::now();
        $token = self::generateSecret(24);
        $hash = LegacyPassword::hash($password);
        $created = false;

        if ($existing !== null) {
            $statement = $pdo->prepare(
                'UPDATE admin_admin
                 SET username = :username,
                     password = :password,
                     nickname = :nickname,
                     status = 1,
                     token = :token,
                     create_time = COALESCE(create_time, :create_time),
                     update_time = :update_time,
                     delete_time = NULL
                 WHERE id = 1'
            );
            $statement->execute([
                ':username' => $username,
                ':password' => $hash,
                ':nickname' => $nickname,
                ':token' => $token,
                ':create_time' => $now,
                ':update_time' => $now,
            ]);
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO admin_admin (id, username, password, nickname, status, token, create_time, update_time, delete_time)
                 VALUES (1, :username, :password, :nickname, 1, :token, :create_time, :update_time, NULL)'
            );
            $statement->execute([
                ':username' => $username,
                ':password' => $hash,
                ':nickname' => $nickname,
                ':token' => $token,
                ':create_time' => $now,
                ':update_time' => $now,
            ]);
            $created = true;
        }

        $bindingExists = self::fetchValue(
            $pdo,
            'SELECT id FROM admin_admin_role WHERE admin_id = 1 AND role_id = 1 LIMIT 1'
        );
        if ($bindingExists === null) {
            $statement = $pdo->prepare(
                'INSERT INTO admin_admin_role (admin_id, role_id) VALUES (1, 1)'
            );
            $statement->execute();
        }

        return [
            'id' => 1,
            'username' => $username,
            'password' => $password,
            'nickname' => $nickname,
            'created' => $created,
            'updated' => true,
        ];
    }

    /**
     * @return array{id:int,username:string,password:string,email:string,name:string,created:bool,updated:bool}
     */
    public static function ensureMerchant(PDO $pdo, array $payload): array
    {
        $userTable = self::businessTable('user');
        $userBasicTable = self::businessTable('userbasic');

        $username = trim((string)($payload['username'] ?? ''));
        $password = trim((string)($payload['password'] ?? ''));
        $email = trim((string)($payload['email'] ?? ''));
        $name = trim((string)($payload['name'] ?? ''));
        $remarks = trim((string)($payload['remarks'] ?? ''));

        if ($username === '') {
            throw new InvalidArgumentException('merchant username is required');
        }

        if ($password === '') {
            throw new InvalidArgumentException('merchant password is required');
        }

        if ($email === '') {
            $email = $username . '@aipay.local';
        }

        if ($name === '') {
            $name = 'Demo Merchant';
        }

        if ($remarks === '') {
            $remarks = 'system seeded merchant';
        }

        self::assertTableExists($pdo, $userTable);
        self::assertTableExists($pdo, $userBasicTable);

        $existing = self::fetchRow(
            $pdo,
            'SELECT id, user_key FROM ' . $userTable . ' WHERE username = :username LIMIT 1',
            [':username' => $username]
        );

        $now = self::now();
        $token = self::generateSecret(24);
        $userKey = trim((string)($existing['user_key'] ?? ''));
        if ($userKey === '') {
            $userKey = self::generateSecret(16);
        }

        $created = false;
        if ($existing !== null) {
            $merchantId = (int)$existing['id'];
            $statement = $pdo->prepare(
                'UPDATE ' . $userTable . '
                 SET password = :password,
                     email = :email,
                     name = :name,
                     user_key = :user_key,
                     token = :token,
                     is_frozen = 0,
                     frozen_reason = NULL,
                     remarks = :remarks
                 WHERE id = :id'
            );
            $statement->execute([
                ':id' => $merchantId,
                ':password' => LegacyPassword::hash($password),
                ':email' => $email,
                ':name' => $name,
                ':user_key' => $userKey,
                ':token' => $token,
                ':remarks' => $remarks,
            ]);
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO ' . $userTable . '
                    (username, password, superior_id, salt, email, mobile, wxpusher_uid, tg_chat_id, is_realName, name, idCard, money, user_key, vip_id, vip_time, feilv, is_bindqq, qq_sid, is_bindwx, wx_sid, googlekey, create_time, token, is_frozen, frozen_reason, remarks)
                 VALUES
                    (:username, :password, 0, NULL, :email, NULL, NULL, NULL, 0, :name, NULL, 0.00, :user_key, 0, NULL, NULL, 0, NULL, 0, NULL, NULL, :create_time, :token, 0, NULL, :remarks)'
            );
            $statement->execute([
                ':username' => $username,
                ':password' => LegacyPassword::hash($password),
                ':email' => $email,
                ':name' => $name,
                ':user_key' => $userKey,
                ':create_time' => $now,
                ':token' => $token,
                ':remarks' => $remarks,
            ]);
            $merchantId = (int)$pdo->lastInsertId();
            $created = true;
        }

        $basic = self::fetchRow(
            $pdo,
            'SELECT id, appkey FROM ' . $userBasicTable . ' WHERE user_id = :user_id LIMIT 1',
            [':user_id' => $merchantId]
        );
        $appkey = trim((string)($basic['appkey'] ?? ''));
        if ($appkey === '') {
            $appkey = self::generateSecret(16);
        }

        if ($basic !== null) {
            $statement = $pdo->prepare(
                'UPDATE ' . $userBasicTable . '
                 SET timeout_method = 2,
                     timeout_url = :timeout_url,
                     timeout_time = :timeout_time,
                     loginfailure = 0,
                     appkey = :appkey,
                     order_tips = :order_tips,
                     is_money_tips = :is_money_tips,
                     money_tips = :money_tips,
                     is_rate = 0,
                     callback_hiddenName = 0
                 WHERE user_id = :user_id'
            );
            $statement->execute([
                ':user_id' => $merchantId,
                ':timeout_url' => '/',
                ':timeout_time' => '180',
                ':appkey' => $appkey,
                ':order_tips' => 'close',
                ':is_money_tips' => 'close',
                ':money_tips' => '0',
            ]);
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO ' . $userBasicTable . '
                    (user_id, timeout_method, timeout_url, timeout_time, loginfailure, appkey, order_tips, is_money_tips, money_tips, is_rate, callback_hiddenName)
                 VALUES
                    (:user_id, 2, :timeout_url, :timeout_time, 0, :appkey, :order_tips, :is_money_tips, :money_tips, 0, 0)'
            );
            $statement->execute([
                ':user_id' => $merchantId,
                ':timeout_url' => '/',
                ':timeout_time' => '180',
                ':appkey' => $appkey,
                ':order_tips' => 'close',
                ':is_money_tips' => 'close',
                ':money_tips' => '0',
            ]);
        }

        return [
            'id' => $merchantId,
            'username' => $username,
            'password' => $password,
            'email' => $email,
            'name' => $name,
            'created' => $created,
            'updated' => true,
        ];
    }

    /**
     * @return array<int,array{type:string,name:string,id:int,created:bool,updated:bool}>
     */
    public static function ensurePaymentMethods(PDO $pdo): array
    {
        $paymentTable = self::businessTable('payment');
        self::assertTableExists($pdo, $paymentTable);

        $definitions = [
            ['type' => 'alipay', 'name' => '支付宝', 'sort' => '100'],
            ['type' => 'wxpay', 'name' => '微信支付', 'sort' => '90'],
            ['type' => 'qqpay', 'name' => 'QQ钱包', 'sort' => '80'],
            ['type' => 'usdt', 'name' => 'USDT', 'sort' => '70'],
        ];

        $summary = [];
        foreach ($definitions as $definition) {
            $existing = self::fetchRow(
                $pdo,
                'SELECT id FROM ' . $paymentTable . ' WHERE type = :type LIMIT 1',
                [':type' => $definition['type']]
            );

            $now = self::now();
            if ($existing !== null) {
                $statement = $pdo->prepare(
                    'UPDATE ' . $paymentTable . '
                     SET name = :name,
                         sort = :sort,
                         status = 1,
                         update_time = :update_time,
                         delete_time = NULL
                     WHERE id = :id'
                );
                $statement->execute([
                    ':id' => (int)$existing['id'],
                    ':name' => $definition['name'],
                    ':sort' => $definition['sort'],
                    ':update_time' => $now,
                ]);
                $summary[] = [
                    'type' => $definition['type'],
                    'name' => $definition['name'],
                    'id' => (int)$existing['id'],
                    'created' => false,
                    'updated' => true,
                ];
                continue;
            }

            $statement = $pdo->prepare(
                'INSERT INTO ' . $paymentTable . ' (name, type, sort, status, create_time, update_time, delete_time)
                 VALUES (:name, :type, :sort, 1, :create_time, NULL, NULL)'
            );
            $statement->execute([
                ':name' => $definition['name'],
                ':type' => $definition['type'],
                ':sort' => $definition['sort'],
                ':create_time' => $now,
            ]);

            $summary[] = [
                'type' => $definition['type'],
                'name' => $definition['name'],
                'id' => (int)$pdo->lastInsertId(),
                'created' => true,
                'updated' => false,
            ];
        }

        return $summary;
    }

    /**
     * @return array<int,array{config_name:string,config_value:string,created:bool}>
     */
    public static function ensureSystemConfigDefaults(PDO $pdo): array
    {
        self::assertTableExists($pdo, 'admin_config');

        $defaults = [
            'is_logOff' => '1',
        ];

        $summary = [];
        foreach ($defaults as $configName => $configValue) {
            $existing = self::fetchValue(
                $pdo,
                'SELECT config_value FROM admin_config WHERE config_name = :config_name LIMIT 1',
                [':config_name' => $configName]
            );

            if ($existing === null) {
                $statement = $pdo->prepare(
                    'INSERT INTO admin_config (config_name, config_value) VALUES (:config_name, :config_value)'
                );
                $statement->execute([
                    ':config_name' => $configName,
                    ':config_value' => $configValue,
                ]);

                $summary[] = [
                    'config_name' => $configName,
                    'config_value' => $configValue,
                    'created' => true,
                ];
                continue;
            }

            $summary[] = [
                'config_name' => $configName,
                'config_value' => (string)$existing,
                'created' => false,
            ];
        }

        return $summary;
    }

    public static function printJson(array $payload): void
    {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    }

    private static function assertTableExists(PDO $pdo, string $table): void
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
        );
        $statement->execute([':table' => $table]);
        if ((int)$statement->fetchColumn() < 1) {
            throw new RuntimeException(sprintf('required table is missing: %s', $table));
        }
    }

    private static function fetchRow(PDO $pdo, string $sql, array $bindings = []): ?array
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($bindings);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return scalar|null
     */
    private static function fetchValue(PDO $pdo, string $sql, array $bindings = [])
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($bindings);
        $value = $statement->fetchColumn();

        return $value === false ? null : $value;
    }
}
