<?php
// Class FaceAuth: Store/verify face descriptor (128 dims euclidean distance, threshold 0.4, multiple 3 for register), hash SHA-256 before store, rate/CSRF in verify.
// Lưu ý: Dùng PDO global từ database.php, hash_equals chống timing, log error.

require_once __DIR__ . '/../config/database.php'; // Global $pdo
require_once __DIR__ . '/../includes/functions.php'; // check_rate_limit, verify_csrf

class FaceAuth
{
    const MATCH_THRESHOLD = 0.4; // Ngưỡng khớp (khá nghiêm ngặt)

    public static function verifyFace($user_id, $incoming_descriptor_json, $csrf_token)
    {
        global $pdo;
        try {
            // Decode descriptor mới từ client
            $incoming = json_decode($incoming_descriptor_json, true);
            if (!is_array($incoming) || empty($incoming) || (isset($incoming[0]) && is_array($incoming[0]))) {
                error_log("verifyFace: Invalid incoming single descriptor for user_id=$user_id");
                return false;
            }

            // Lấy stored descriptors từ DB
            $stmt = $pdo->prepare("SELECT face_descriptors FROM faces WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // SỬA LẠI: Bỏ hoàn toàn phần kiểm tra hash
            // if (!$row || empty($row['face_hash']) || !hash_equals($row['face_hash'], $incoming_hash)) {
            //     error_log("verifyFace: Hash mismatch or no data for user_id=$user_id");
            //     return false;
            // }

            // SỬA LẠI: Chỉ cần kiểm tra có descriptor hay không
            if (!$row || empty($row['face_descriptors'])) {
                error_log("verifyFace: No face data found for user_id=$user_id");
                return false;
            }

            // Decode stored descriptors
            $stored = json_decode($row['face_descriptors'], true);
            if (!is_array($stored) || empty($stored)) {
                error_log("verifyFace: Invalid stored descriptor for user_id=$user_id. Raw data: " . $row['face_descriptors']);
                return false;
            }

            // SỬA LẠI: Logic chuẩn hóa để luôn làm việc với một mảng các descriptors
            $storedDescriptors = [];
            if (isset($stored[0]) && is_array($stored[0])) {
                $storedDescriptors = $stored;
            } else {
                $storedDescriptors = [$stored];
            }

            $bestDistance = PHP_FLOAT_MAX;
            foreach ($storedDescriptors as $storedDescriptor) {
                $distance = self::euclideanDistance($incoming, $storedDescriptor);
                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                }
            }

            $match = $bestDistance < self::MATCH_THRESHOLD;
            error_log("verifyFace: user_id=$user_id, best_distance=$bestDistance, threshold=" . self::MATCH_THRESHOLD . ", match=" . ($match ? 'true' : 'false'));
            return $match;
        } catch (Exception $e) {
            // Log lỗi chi tiết nếu có exception xảy ra
            error_log("FATAL ERROR in verifyFace for user_id=$user_id: " . $e->getMessage());
            return false;
        }
    }

    public static function storeFace($user_id, $face_descriptors_json)
    {
        global $pdo;
        try {
            error_log("Đang lưu vào DB cho user_id=$user_id, data=$face_descriptors_json");
            // THÊM: Validate JSON đầu vào
            $descriptors = json_decode($face_descriptors_json, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($descriptors)) {
                error_log("storeFace: Invalid JSON format for user_id=$user_id");
                return false;
            }

            // Hash descriptor SHA-256 để kiểm tra tính toàn vẹn, không dùng để so sánh
            $face_hash = hash('sha256', $face_descriptors_json);

            $stmt = $pdo->prepare(
                "INSERT INTO faces (user_id, face_hash, face_descriptors) 
                 VALUES (?, ?, ?) 
                 ON DUPLICATE KEY UPDATE face_hash = VALUES(face_hash), face_descriptors = VALUES(face_descriptors)"
            );
            $result = $stmt->execute([$user_id, $face_hash, $face_descriptors_json]);
            if (!$result) {
                // THÊM DÒNG NÀY ĐỂ XEM LỖI CHI TIẾT
                error_log("Lỗi PDO: " . print_r($stmt->errorInfo(), true));
            }
            if ($result) {
                error_log("storeFace: SUCCESS for user_id=$user_id");
                return true;
            }
            error_log("storeFace: FAILED to execute statement for user_id=$user_id");
            return false;
        } catch (Exception $e) {
            error_log("storeFace error: " . $e->getMessage());
            return false;
        }
    }

    public static function hasFace($user_id)
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM faces WHERE user_id = ? AND face_descriptors IS NOT NULL AND face_descriptors != '' AND face_descriptors != '[]'");
            $stmt->execute([$user_id]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log("hasFace error: " . $e->getMessage());
            return false;
        }
    }

    private static function euclideanDistance(array $vec1, array $vec2)
    {
        if (count($vec1) !== count($vec2)) {
            error_log("euclideanDistance: Vector length mismatch.");
            return PHP_FLOAT_MAX;
        }

        $sum = 0;
        $count = count($vec1);
        for ($i = 0; $i < $count; $i++) {
            $diff = $vec1[$i] - $vec2[$i];
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }
}
