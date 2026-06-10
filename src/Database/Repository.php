<?php

namespace Phoenix\Database;

abstract class Repository
{
    protected string $table;
    protected string $entity;

    private static array $reflectionCache = [];

    public function find(int $id): ?object
    {
        $stmt = Connection::get()->prepare("SELECT * FROM {$this->table} WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $data ? $this->mapToEntity($data) : null;
    }

    public function findAll(): array
    {
        $stmt = Connection::get()->prepare("SELECT * FROM {$this->table}");
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn ($data) => $this->mapToEntity($data), $rows);
    }

    public function save(object $entity): void
    {
        // Override in subclass for insert/update logic
    }

    protected function mapToEntity(array $data): object
    {
        $entityClass = $this->entity;

        if (!isset(self::$reflectionCache[$entityClass])) {
            if (!class_exists($entityClass)) {
                self::$reflectionCache[$entityClass] = null;
            } else {
                $reflection = new \ReflectionClass($entityClass);
                $constructor = $reflection->getConstructor();
                $params = $constructor ? $constructor->getParameters() : [];
                self::$reflectionCache[$entityClass] = $params;
            }
        }

        $params = self::$reflectionCache[$entityClass];

        if ($params !== null && count($params) > 0) {
            $args = [];
            foreach ($params as $param) {
                $name = $param->getName();
                $args[] = $data[$name] ?? $param->getDefaultValue();
            }

            return new $entityClass(...$args);
        }

        return (object) $data;
    }

    public static function clearReflectionCache(): void
    {
        self::$reflectionCache = [];
    }
}
