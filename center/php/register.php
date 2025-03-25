<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");

require_once "db.php"; // DB connection
require_once "verify_mail.php"; // Email verification

global $conn;

$first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
$last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$date_of_birth = (!empty($_POST['date_of_birth'])) ? trim($_POST['date_of_birth']) : NULL;

if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "Fields required"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "message" => "Invalid email"]);
    exit;
}

$sql_check = "SELECT user_id FROM user WHERE email = ?";
$stmt = $conn->prepare($sql_check);
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Email already registered"]);
    exit;
}
$stmt->close();

$hashed_password = password_hash($password, PASSWORD_BCRYPT);
$verification_token = bin2hex(random_bytes(16));
$verification_token_hash = hash('sha256', $verification_token);


# USER TABLE
$sql_insert_user = "INSERT INTO user (email, password_hash, created_at, updated_at, verification_token, is_verified) VALUES (?, ?, NOW(), NOW(), ?, 0)";
$stmt_user = $conn->prepare($sql_insert_user);
$stmt_user->bind_param("sss", $email, $hashed_password, $verification_token_hash);

if ($stmt_user->execute()) {
    $user_id = $stmt_user->insert_id;
    $stmt_user->close();

    # USER PROFILE TABLE
    $sql_insert_profile = "INSERT INTO user_profile (user_id, first_name, last_name, date_of_birth) VALUES (?, ?, ?, ?)";
    $stmt_profile = $conn->prepare($sql_insert_profile);
    $stmt_profile->bind_param("isss", $user_id, $first_name, $last_name, $date_of_birth);

    if ($stmt_profile->execute()) {
        $stmt_profile->close();

        // Send email verification
        if (sendVerificationEmail($email, $verification_token)) {
            echo json_encode(["status" => "success", "message" => "Registration successful."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Registration successful, email not sent."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "User profile creation failed"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Registration error"]);
}

$conn->close();
?>