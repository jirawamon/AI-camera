<?php

require_once __DIR__ . '/config.php';

class Request {
    public function method() {
        return $_SERVER["REQUEST_METHOD"];
    }

    public function body() {
        return json_decode(file_get_contents("php://input"), true);
    }

    public function header($name) {
        $key = "HTTP_" . strtoupper(str_replace("-", "_", $name));
        return $_SERVER[$key] ?? null;
    }

    public function ip() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

class Response {
    public function json($data, $status = 200) {
        http_response_code($status);
        header("Content-Type: application/json");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type");
        echo json_encode($data);
        exit;
    }
}

class WebhookService {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function process($data, $ip) {
        // Validate required fields
        if (empty($data)) {
            return ['success' => false, 'error' => 'No data received'];
        }

        try {
            $stmt = $this->db->prepare("
                INSERT INTO webhook_events 
                (alarm_id, coordinate, equipment_id, equipment_name, send_time, 
                 event_type_id, task_id, task_name, str_res, 
                 installation_area, installation_location, box_code,
                 image_data, ip_address)
                VALUES 
                (:alarm_id, :coordinate, :equipment_id, :equipment_name, :send_time, 
                 :event_type_id, :task_id, :task_name, :str_res, 
                 :installation_area, :installation_location, :box_code,
                 :image_data, :ip_address)
            ");

            $stmt->execute([
                ':alarm_id' => $data['id'] ?? null,
                ':coordinate' => $data['coordinate'] ?? null,
                ':equipment_id' => $data['equipmentId'] ?? null,
                ':equipment_name' => $data['equipmentName'] ?? null,
                ':send_time' => $data['sendTime'] ?? null,
                ':event_type_id' => $data['eventTypeId'] ?? null,
                ':task_id' => $data['taskId'] ?? null,
                ':task_name' => $data['taskName'] ?? null,
                ':str_res' => $data['strRes'] ?? null,
                ':installation_area' => $data['installationArea'] ?? null,
                ':installation_location' => $data['installationLocation'] ?? null,
                ':box_code' => $data['boxCode'] ?? null,
                ':image_data' => $data['imageUrl'] ?? null,
                ':ip_address' => $ip
            ]);

            return ['success' => true, 'id' => $this->db->lastInsertId()];

        } catch (PDOException $e) {
            error_log("Webhook DB Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
}

class WebhookController {
    private $req;
    private $res;
    private $service;

    public function __construct($req, $res, $service) {
        $this->req = $req;
        $this->res = $res;
        $this->service = $service;
    }

    public function handle() {
        // Handle CORS preflight
        if ($this->req->method() === "OPTIONS") {
            $this->res->json(["status" => "ok"]);
        }

        if ($this->req->method() !== "POST") {
            $this->res->json(["error" => "POST only"], 405);
        }

        $data = $this->req->body();
        $ip = $this->req->ip();

        $result = $this->service->process($data, $ip);

        if ($result['success']) {
            $this->res->json($result);
        } else {
            $this->res->json($result, 400);
        }
    }
}

// ===== Run =====

$req = new Request();
$res = new Response();
$service = new WebhookService();

$controller = new WebhookController($req, $res, $service);
$controller->handle();

?>
