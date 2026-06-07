<?php

namespace Phoenix\Database;

abstract class Repository
{
    protected string $table;
    protected string $entity;

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

        return array_map(fn($data) => $this->mapToEntity($data), $rows);
    }

    public function save(object $entity): void
    {
        // Override in subclass for insert/update logic
    }

    protected function mapToEntity(array $data): object
    {
        if (class_exists($this->entity)) {
            $reflection = new \ReflectionClass($this->entity);
            $constructor = $reflection->getConstructor();

            if ($constructor && $constructor->getNumberOfParameters() > 0) {
                $params = $constructor->getParameters();
                $args = [];
                foreach ($params as $param) {
                    $name = $param->getName();
                    $args[] = $data[$name] ?? $param->getDefaultValue();
                }
                return new ($this->entity)(...$args);
            }
        }

        return (object) $data;
    }
}
