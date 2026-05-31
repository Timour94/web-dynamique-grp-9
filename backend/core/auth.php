<?php
function register_user(array $data): array
{
    $pdo = getDB();
    $errors = [];

    $prenom = clean_string($data['prenom'] ?? '', 80);
    $nom = clean_string($data['nom'] ?? '', 80);
    $email = strtolower(clean_string($data['email'] ?? '', 190));
    $password = (string)($data['password'] ?? '');
    $confirm = (string)($data['confirm_password'] ?? '');
    $role = validate_role_value((string)($data['role'] ?? 'acheteur'), $errors);
    $telephone = clean_string($data['telephone'] ?? '', 30);
    $adresse = clean_string($data['adresse'] ?? '', 255);
    $ville = clean_string($data['ville'] ?? '', 100);
    $codePostal = clean_string($data['code_postal'] ?? '', 20);

    validate_required($prenom, 'prénom', $errors);
    validate_required($nom, 'nom', $errors);
    validate_email_value($email, $errors);
    validate_password_value($password, $errors);

    if ($password !== $confirm) {
        $errors[] = 'Les deux mots de passe ne correspondent pas.';
    }

    $stmt = $pdo->prepare('SELECT id_user FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $errors[] = 'Un compte existe déjà avec cette adresse e-mail.';
    }

    if ($errors) {
        return ['success' => false, 'errors' => $errors];
    }

    $roleId = get_role_id($role);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (id_role, nom, prenom, email, mot_de_passe, telephone, adresse, ville, code_postal, statut_compte, date_creation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "actif", NOW())');
    $stmt->execute([$roleId, $nom, $prenom, $email, $hash, $telephone, $adresse, $ville, $codePostal]);
    $userId = (int)$pdo->lastInsertId();
    add_notification($userId, 'Bienvenue sur Mercato Nova', 'Votre compte a bien été créé.', 'compte', 'espace-client.php');
    login_by_id($userId);
    return ['success' => true, 'errors' => []];
}

function login_user(string $email, string $password): bool
{
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT u.*, r.nom_role FROM users u JOIN roles r ON r.id_role = u.id_role WHERE u.email = ? LIMIT 1');
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['mot_de_passe'])) {
        return false;
    }
    if ($user['statut_compte'] !== 'actif') {
        flash('error', 'Votre compte est bloqué. Contactez un administrateur.');
        return false;
    }

    $_SESSION['user_id'] = (int)$user['id_user'];
    $_SESSION['role'] = $user['nom_role'];
    session_regenerate_id(true);
    $pdo->prepare('UPDATE users SET date_derniere_connexion = NOW() WHERE id_user = ?')->execute([(int)$user['id_user']]);
    return true;
}

function login_by_id(int $userId): void
{
    $user = get_user_by_id($userId);
    if ($user) {
        $_SESSION['user_id'] = (int)$user['id_user'];
        $_SESSION['role'] = $user['nom_role'];
        session_regenerate_id(true);
    }
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return get_user_by_id((int)$_SESSION['user_id']);
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function is_logged_in(): bool
{
    return current_user_id() !== null;
}

function user_role(): ?string
{
    return $_SESSION['role'] ?? null;
}
