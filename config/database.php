<?php
/**
 * طبقة الاتصال بقاعدة البيانات - PDO
 * Saree3 - تطبيق توصيل ومارت
 * @version 1.0
 */

// =====================================================
// إعدادات الاتصال بقاعدة البيانات
// =====================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'smm2355_almarket');
define('DB_USER', 'smm2355_almarket');
define('DB_PASS', 'smm2355_almarket');
define('DB_CHARSET', 'utf8mb4');
define('DB_PORT', 3306);

// =====================================================
// خيارات PDO
// =====================================================
$db_options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
    PDO::ATTR_PERSISTENT         => false,
];

// =====================================================
// إنشاء اتصال PDO
// =====================================================
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $db = new PDO($dsn, DB_USER, DB_PASS, $db_options);
    
} catch (PDOException $e) {
    // تسجيل الخطأ
    error_log("Database Connection Error: " . $e->getMessage());
    
    // عرض رسالة خطأ مناسبة
    if (ENVIRONMENT === 'development') {
        die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
    } else {
        die("عذراً، حدث خطأ في النظام. يرجى المحاولة لاحقاً.");
    }
}

// =====================================================
// كلاس قاعدة البيانات (طبقة تجريد إضافية)
// =====================================================
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        global $db;
        $this->connection = $db;
    }
    
    /**
     * الحصول على نسخة واحدة من الكلاس (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * الحصول على اتصال PDO
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * تنفيذ استعلام SELECT وإرجاع صف واحد
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    /**
     * تنفيذ استعلام SELECT وإرجاع كل الصفوف
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * تنفيذ استعلام SELECT وإرجاع قيمة عمود واحد
     */
    public function fetchColumn($sql, $params = [], $column = 0) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn($column);
    }
    
    /**
     * تنفيذ استعلام INSERT
     */
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        $fields_str = implode(', ', $fields);
        
        $sql = "INSERT INTO {$table} ({$fields_str}) VALUES ({$placeholders})";
        $stmt = $this->connection->prepare($sql);
        
        foreach ($data as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        
        $stmt->execute();
        return $this->connection->lastInsertId();
    }
    
    /**
     * تنفيذ استعلام UPDATE
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        $params = [];

        foreach ($data as $field => $value) {
            $paramKey = ':set_' . $field;
            $setParts[] = "{$field} = {$paramKey}";
            $params[$paramKey] = $value;
        }

        $whereClause = $where;

        if (strpos($whereClause, '?') !== false) {
            $whereValues = array_values($whereParams);
            foreach ($whereValues as $i => $value) {
                $whereKey = ':where_' . $i;
                $whereClause = preg_replace('/\?/', $whereKey, $whereClause, 1);
                $params[$whereKey] = $value;
            }
        } else {
            foreach ($whereParams as $key => $value) {
                $whereKey = str_starts_with((string)$key, ':') ? (string)$key : ':' . $key;
                $params[$whereKey] = $value;
            }
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE {$whereClause}";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * تنفيذ استعلام DELETE
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * بدء معاملة
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * تأكيد المعاملة
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * التراجع عن المعاملة
     */
    public function rollback() {
        return $this->connection->rollback();
    }
    
    /**
     * الحصول على آخر ID تم إدراجه
     */
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    /**
     * تنفيذ استعلام عام
     */
    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}

// =====================================================
// دالة مساعدة للحصول على اتصال سريع
// =====================================================
function db() {
    global $db;
    return $db;
}

/**
 * إرجاع كائن PDO الخام عند الحاجة للـ transactions المتقدمة.
 */
function get_pdo() {
    global $db;
    return $db;
}

// =====================================================
// دالة مساعدة لتنفيذ استعلام سريع
// =====================================================
function db_query($sql, $params = []) {
    global $db;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// =====================================================
// دالة مساعدة لجلب صف واحد
// =====================================================
function db_fetch($sql, $params = []) {
    global $db;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

// =====================================================
// دالة مساعدة لجلب كل الصفوف
// =====================================================
function db_fetch_all($sql, $params = []) {
    global $db;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// =====================================================
// دالة مساعدة لإدراج بيانات
// =====================================================
function db_insert($table, $data) {
    $db = Database::getInstance();
    return $db->insert($table, $data);
}

// =====================================================
// دالة مساعدة لتحديث بيانات
// =====================================================
function db_update($table, $data, $where, $whereParams = []) {
    $db = Database::getInstance();
    return $db->update($table, $data, $where, $whereParams);
}

// =====================================================
// دالة مساعدة لحذف بيانات
// =====================================================
function db_delete($table, $where, $params = []) {
    $db = Database::getInstance();
    return $db->delete($table, $where, $params);
}
