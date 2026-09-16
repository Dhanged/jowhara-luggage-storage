<?php
namespace App\Repositories;

class GuestRepository
{
    public function findById(int $id): ?array
    {
        return \db_fetch_one("SELECT * FROM guests WHERE guest_id = ? AND deleted_at IS NULL LIMIT 1", "i", [$id]);
    }

    public function findByName(string $name): ?array
    {
        return \db_fetch_one("SELECT guest_id FROM guests WHERE guest_name = ? AND deleted_at IS NULL LIMIT 1", "s", [$name]);
    }

    public function create(string $name, ?string $phone = null, ?string $roomNo = null, ?string $email = null): int
    {
        \db_execute(
            "INSERT INTO guests (guest_name, phone, room_no, email) VALUES (?, ?, ?, ?)",
            "ssss",
            [$name, $phone, $roomNo, $email]
        );
        return \db_insert_id();
    }
}
