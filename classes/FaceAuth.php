<?php
class FaceAuth {
    // Tăng ngưỡng để dễ khớp hơn (0.4 = nghiêm, 1.5 = lỏng)
    const MATCH_THRESHOLD = 0.4;
    
    public static function verifyFace($user_id, $incoming_descriptor) {
        $conn = new mysqli('localhost', 'root', '', 'mfa_demo');
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            return false;
        }

        $stmt = $conn->prepare("SELECT face_descriptors FROM faces WHERE user_id = ?");
        if ($stmt === false) {
            error_log("Prepare failed in verifyFace: " . $conn->error);
            return false;
        }

        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        $conn->close();

        if (!$row || empty($row['face_descriptors'])) {
            error_log("verifyFace: No face data found for user_id=$user_id");
            return false;
        }

        // Decode descriptor mới từ client
        $incoming = json_decode($incoming_descriptor, true);
        if (!is_array($incoming) || count($incoming) === 0) {
            error_log("verifyFace: Invalid incoming descriptor for user_id=$user_id");
            return false;
        }

        // Decode các descriptors đã lưu
        $stored = json_decode($row['face_descriptors'], true);
        if (!is_array($stored) || count($stored) === 0) {
            error_log("verifyFace: Invalid stored descriptor for user_id=$user_id");
            return false;
        }

        // Kiểm tra xem stored là mảng của descriptors hay 1 descriptor đơn
        $storedDescriptors = [];
        if (isset($stored[0]) && is_array($stored[0])) {
            $storedDescriptors = $stored;
        } else {
            $storedDescriptors = [$stored];
        }

        // So sánh với từng descriptor đã lưu
        $bestDistance = PHP_FLOAT_MAX;
        foreach ($storedDescriptors as $index => $storedDesc) {
            $distance = self::euclideanDistance($incoming, $storedDesc);
            error_log("verifyFace: Distance[$index] = $distance");
            
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
            }
        }

        error_log("verifyFace: Best distance = $bestDistance (threshold=" . self::MATCH_THRESHOLD . ")");
        
        if ($bestDistance <= self::MATCH_THRESHOLD) {
            error_log("verifyFace: MATCH SUCCESS for user_id=$user_id");
            return true;
        } else {
            error_log("verifyFace: MATCH FAILED for user_id=$user_id");
            return false;
        }
    }

    private static function euclideanDistance($vec1, $vec2) {
        if (count($vec1) !== count($vec2)) {
            error_log("euclideanDistance: Length mismatch: " . count($vec1) . " vs " . count($vec2));
            return PHP_FLOAT_MAX;
        }

        $sum = 0;
        for ($i = 0; $i < count($vec1); $i++) {
            $diff = $vec1[$i] - $vec2[$i];
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }

    public static function storeFace($user_id, $face_descriptors_json) {
        $conn = new mysqli('localhost', 'root', '', 'mfa_demo');
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            return;
        }

        $stmt = $conn->prepare("INSERT INTO faces (user_id, face_descriptors) VALUES (?, ?) ON DUPLICATE KEY UPDATE face_descriptors = ?");
        if ($stmt === false) {
            error_log("Prepare failed in storeFace: " . $conn->error);
            return;
        }

        $stmt->bind_param("iss", $user_id, $face_descriptors_json, $face_descriptors_json);
        $stmt->execute();
        error_log("storeFace: SUCCESS for user_id=$user_id");
        $stmt->close();
        $conn->close();
    }

    public static function hasFace($user_id) {
        $conn = new mysqli('localhost', 'root', '', 'mfa_demo');
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            return false;
        }

        $stmt = $conn->prepare("SELECT COUNT(*) FROM faces WHERE user_id = ? AND face_descriptors IS NOT NULL AND face_descriptors != ''");
        if ($stmt === false) {
            error_log("Prepare failed in hasFace: " . $conn->error);
            return false;
        }

        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_row()[0];
        $stmt->close();
        $conn->close();
        return $count > 0;
    }
}
?>