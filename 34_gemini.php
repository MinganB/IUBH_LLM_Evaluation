<?php

class Profile
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getProfile(int $userId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM profiles WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch();
    }

    public function updateProfile(int $userId, array $data)
    {
        $fields = [];
        $params = ['user_id' => $userId];

        foreach ($data as $key => $value) {
            if (!in_array($key, ['first_name', 'last_name', 'email', 'phone', 'address_line1', 'address_line2', 'city', 'state', 'zip_code', 'country', 'billing_address_line1', 'billing_address_line2', 'billing_city', 'billing_state', 'billing_zip_code', 'billing_country'])) {
                continue;
            }
            $fields[] = "`{$key}` = :{$key}";
            $params[$key] = $value;
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE profiles SET " . implode(', ', $fields) . " WHERE user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function createProfile(int $userId, array $data)
    {
        $fields = ['user_id'];
        $placeholders = [':user_id'];
        $params = ['user_id' => $userId];

        foreach ($data as $key => $value) {
            if (!in_array($key, ['first_name', 'last_name', 'email', 'phone', 'address_line1', 'address_line2', 'city', 'state', 'zip_code', 'country', 'billing_address_line1', 'billing_address_line2', 'billing_city', 'billing_state', 'billing_zip_code', 'billing_country'])) {
                continue;
            }
            $fields[] = "`{$key}`";
            $placeholders[] = ":{$key}";
            $params[$key] = $value;
        }

        $sql = "INSERT INTO profiles (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
}
?>