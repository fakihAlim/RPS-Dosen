<?php
/**
 * Database-Based PHP Session Handler
 * Menyimpan session di tabel MySQL `php_sessions` agar kompatibel
 * dengan semua jenis shared hosting tanpa bergantung pada filesystem.
 */

class DbSessionHandler implements SessionHandlerInterface
{
    private $pdo;
    private $table = 'php_sessions';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $stmt = $this->pdo->prepare(
            "SELECT `data` FROM `{$this->table}` WHERE `id` = ? AND `expires_at` > NOW() LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['data'] : '';
    }

    public function write(string $id, string $data): bool
    {
        $lifetime = (int) ini_get('session.gc_maxlifetime');
        $expires = date('Y-m-d H:i:s', time() + $lifetime);

        $stmt = $this->pdo->prepare(
            "REPLACE INTO `{$this->table}` (`id`, `data`, `expires_at`) VALUES (?, ?, ?)"
        );
        return $stmt->execute([$id, $data, $expires]);
    }

    public function destroy(string $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE `id` = ?");
        return $stmt->execute([$id]);
    }

    public function gc(int $maxLifetime): int|false
    {
        $stmt = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE `expires_at` < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
