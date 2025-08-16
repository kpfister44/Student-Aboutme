<?php
session_start();

// Database initialization
function initDatabase() {
    $db = new PDO('sqlite:database.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create users table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        name TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'student',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create courses table
    $db->exec("CREATE TABLE IF NOT EXISTS courses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        join_code TEXT UNIQUE NOT NULL,
        teacher_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES users (id)
    )");
    
    // Create course enrollments table
    $db->exec("CREATE TABLE IF NOT EXISTS course_enrollments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        course_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users (id),
        FOREIGN KEY (course_id) REFERENCES courses (id),
        UNIQUE(user_id, course_id)
    )");
    
    // Create student profiles table
    $db->exec("CREATE TABLE IF NOT EXISTS student_profiles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        course_id INTEGER NOT NULL,
        preferred_name TEXT,
        pronouns TEXT,
        major TEXT,
        goals TEXT,
        fun_fact TEXT,
        learning_needs TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users (id),
        FOREIGN KEY (course_id) REFERENCES courses (id),
        UNIQUE(user_id, course_id)
    )");
    
    return $db;
}

// Authentication functions
function registerUser($db, $email, $password, $name, $role = 'student') {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $db->prepare("INSERT INTO users (email, password_hash, name, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$email, $hashedPassword, $name, $role]);
        return $db->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

function loginUser($db, $email, $password) {
    $stmt = $db->prepare("SELECT id, password_hash, name, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        return true;
    }
    
    return false;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ?page=login');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['user_role'] !== $role) {
        header('Location: ?page=dashboard');
        exit;
    }
}

// Helper functions
function generateJoinCode() {
    return strtoupper(substr(md5(time() . rand()), 0, 8));
}

function getCurrentUser($db) {
    if (!isLoggedIn()) return null;
    
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserCourses($db, $userId) {
    $stmt = $db->prepare("
        SELECT c.*, u.name as teacher_name 
        FROM courses c 
        JOIN course_enrollments ce ON c.id = ce.course_id 
        JOIN users u ON c.teacher_id = u.id
        WHERE ce.user_id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTeacherCourses($db, $teacherId) {
    $stmt = $db->prepare("SELECT * FROM courses WHERE teacher_id = ?");
    $stmt->execute([$teacherId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Initialize database
$db = initDatabase();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'auth':
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $name = trim($_POST['name'] ?? '');
            $role = $_POST['role'] ?? 'student';
            
            // Try login first
            if (loginUser($db, $email, $password)) {
                header('Location: ?page=dashboard');
                exit;
            }
            
            // If login fails and name provided, register
            if ($name) {
                $userId = registerUser($db, $email, $password, $name, $role);
                if ($userId) {
                    loginUser($db, $email, $password);
                    header('Location: ?page=dashboard');
                    exit;
                } else {
                    $error = "Registration failed. Email may already exist.";
                }
            } else {
                $error = "Invalid login credentials.";
            }
            break;
            
        case 'create_course':
            requireRole('teacher');
            $courseName = trim($_POST['course_name']);
            $joinCode = generateJoinCode();
            
            $stmt = $db->prepare("INSERT INTO courses (name, join_code, teacher_id) VALUES (?, ?, ?)");
            if ($stmt->execute([$courseName, $joinCode, $_SESSION['user_id']])) {
                $success = "Course created successfully! Join code: $joinCode";
            } else {
                $error = "Failed to create course.";
            }
            break;
            
        case 'join_course':
            requireLogin();
            $joinCode = strtoupper(trim($_POST['join_code']));
            
            $stmt = $db->prepare("SELECT id FROM courses WHERE join_code = ?");
            $stmt->execute([$joinCode]);
            $course = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($course) {
                try {
                    $stmt = $db->prepare("INSERT INTO course_enrollments (user_id, course_id) VALUES (?, ?)");
                    $stmt->execute([$_SESSION['user_id'], $course['id']]);
                    $success = "Successfully joined course!";
                } catch (PDOException $e) {
                    $error = "You are already enrolled in this course.";
                }
            } else {
                $error = "Invalid join code.";
            }
            break;
            
        case 'save_profile':
            requireLogin();
            $courseId = $_POST['course_id'];
            $preferredName = trim($_POST['preferred_name']);
            $pronouns = trim($_POST['pronouns']);
            $major = trim($_POST['major']);
            $goals = trim($_POST['goals']);
            $funFact = trim($_POST['fun_fact']);
            $learningNeeds = trim($_POST['learning_needs']);
            
            // Check if profile exists
            $stmt = $db->prepare("SELECT id FROM student_profiles WHERE user_id = ? AND course_id = ?");
            $stmt->execute([$_SESSION['user_id'], $courseId]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($profile) {
                // Update existing profile
                $stmt = $db->prepare("
                    UPDATE student_profiles 
                    SET preferred_name = ?, pronouns = ?, major = ?, goals = ?, fun_fact = ?, learning_needs = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE user_id = ? AND course_id = ?
                ");
                $stmt->execute([$preferredName, $pronouns, $major, $goals, $funFact, $learningNeeds, $_SESSION['user_id'], $courseId]);
            } else {
                // Create new profile
                $stmt = $db->prepare("
                    INSERT INTO student_profiles (user_id, course_id, preferred_name, pronouns, major, goals, fun_fact, learning_needs)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$_SESSION['user_id'], $courseId, $preferredName, $pronouns, $major, $goals, $funFact, $learningNeeds]);
            }
            
            $success = "Profile saved successfully!";
            break;
            
        case 'logout':
            session_destroy();
            header('Location: ?page=login');
            exit;
    }
}

// Get current page
$page = $_GET['page'] ?? (isLoggedIn() ? 'dashboard' : 'login');
$courseId = $_GET['course_id'] ?? null;
$search = $_GET['search'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentIntro</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            border-bottom: 1px solid #e9ecef;
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: #007bff;
        }
        
        .nav {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .nav a {
            color: #666;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        
        .nav a:hover, .nav a.active {
            background-color: #e9ecef;
            color: #333;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .btn {
            background-color: #007bff;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.2s;
        }
        
        .btn:hover {
            background-color: #0056b3;
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #545b62;
        }
        
        .btn-danger {
            background-color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .alert {
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .grid {
            display: grid;
            gap: 1.5rem;
        }
        
        .grid-2 {
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        }
        
        .grid-3 {
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }
        
        .profile-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        
        .profile-card h3 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        .profile-field {
            margin-bottom: 0.75rem;
        }
        
        .profile-field strong {
            color: #666;
            display: block;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .search-box {
            margin-bottom: 2rem;
        }
        
        .search-box input {
            width: 100%;
            max-width: 400px;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .course-info {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .join-code {
            font-family: monospace;
            font-size: 1.2rem;
            font-weight: bold;
            color: #007bff;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav {
                flex-wrap: wrap;
            }
            
            .card {
                padding: 1rem;
            }
            
            .grid-2, .grid-3 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo">StudentIntro</div>
            <?php if (isLoggedIn()): ?>
            <nav class="nav">
                <a href="?page=dashboard" <?= $page === 'dashboard' ? 'class="active"' : '' ?>>Dashboard</a>
                <?php if ($_SESSION['user_role'] === 'teacher'): ?>
                <a href="?page=courses" <?= $page === 'courses' ? 'class="active"' : '' ?>>My Courses</a>
                <?php endif; ?>
                <span>Hello, <?= htmlspecialchars($_SESSION['user_name']) ?></span>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="btn btn-secondary">Logout</button>
                </form>
            </nav>
            <?php endif; ?>
        </div>
    </header>

    <div class="container">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php
        switch ($page) {
            case 'login':
                if (isLoggedIn()) {
                    header('Location: ?page=dashboard');
                    exit;
                }
                ?>
                <div class="card">
                    <h1>Welcome to StudentIntro</h1>
                    <p>Login with your email and password, or register by providing your name as well.</p>
                    
                    <form method="post">
                        <input type="hidden" name="action" value="auth">
                        
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password:</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="name">Name (required for registration):</label>
                            <input type="text" id="name" name="name" placeholder="Leave blank to login only">
                        </div>
                        
                        <div class="form-group">
                            <label for="role">Role (for registration):</label>
                            <select id="role" name="role">
                                <option value="student">Student</option>
                                <option value="teacher">Teacher</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn">Login / Register</button>
                    </form>
                </div>
                <?php
                break;
                
            case 'dashboard':
                requireLogin();
                
                if ($_SESSION['user_role'] === 'student') {
                    $courses = getUserCourses($db, $_SESSION['user_id']);
                    ?>
                    <h1>Student Dashboard</h1>
                    
                    <div class="grid grid-2">
                        <div class="card">
                            <h2>Join a Course</h2>
                            <form method="post">
                                <input type="hidden" name="action" value="join_course">
                                <div class="form-group">
                                    <label for="join_code">Course Join Code:</label>
                                    <input type="text" id="join_code" name="join_code" placeholder="Enter join code" required>
                                </div>
                                <button type="submit" class="btn">Join Course</button>
                            </form>
                        </div>
                        
                        <div class="card">
                            <h2>My Courses</h2>
                            <?php if (empty($courses)): ?>
                                <p>You haven't joined any courses yet.</p>
                            <?php else: ?>
                                <?php foreach ($courses as $course): ?>
                                    <div style="margin-bottom: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 4px;">
                                        <h3><?= htmlspecialchars($course['name']) ?></h3>
                                        <p>Teacher: <?= htmlspecialchars($course['teacher_name']) ?></p>
                                        <a href="?page=profile&course_id=<?= $course['id'] ?>" class="btn">Edit Profile</a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                } else {
                    // Teacher dashboard
                    $courses = getTeacherCourses($db, $_SESSION['user_id']);
                    ?>
                    <h1>Teacher Dashboard</h1>
                    
                    <div class="grid grid-2">
                        <div class="card">
                            <h2>Create New Course</h2>
                            <form method="post">
                                <input type="hidden" name="action" value="create_course">
                                <div class="form-group">
                                    <label for="course_name">Course Name:</label>
                                    <input type="text" id="course_name" name="course_name" required>
                                </div>
                                <button type="submit" class="btn">Create Course</button>
                            </form>
                        </div>
                        
                        <div class="card">
                            <h2>My Courses</h2>
                            <?php if (empty($courses)): ?>
                                <p>You haven't created any courses yet.</p>
                            <?php else: ?>
                                <?php foreach ($courses as $course): ?>
                                    <div style="margin-bottom: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 4px;">
                                        <h3><?= htmlspecialchars($course['name']) ?></h3>
                                        <p>Join Code: <span class="join-code"><?= htmlspecialchars($course['join_code']) ?></span></p>
                                        <a href="?page=view_profiles&course_id=<?= $course['id'] ?>" class="btn">View Student Profiles</a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                }
                break;
                
            case 'profile':
                requireLogin();
                
                if (!$courseId) {
                    header('Location: ?page=dashboard');
                    exit;
                }
                
                // Get course info
                $stmt = $db->prepare("SELECT name FROM courses WHERE id = ?");
                $stmt->execute([$courseId]);
                $course = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$course) {
                    header('Location: ?page=dashboard');
                    exit;
                }
                
                // Get existing profile
                $stmt = $db->prepare("SELECT * FROM student_profiles WHERE user_id = ? AND course_id = ?");
                $stmt->execute([$_SESSION['user_id'], $courseId]);
                $profile = $stmt->fetch(PDO::FETCH_ASSOC);
                ?>
                <h1>About Me Profile - <?= htmlspecialchars($course['name']) ?></h1>
                
                <div class="card">
                    <form method="post">
                        <input type="hidden" name="action" value="save_profile">
                        <input type="hidden" name="course_id" value="<?= $courseId ?>">
                        
                        <div class="form-group">
                            <label for="preferred_name">Preferred Name:</label>
                            <input type="text" id="preferred_name" name="preferred_name" 
                                   value="<?= htmlspecialchars($profile['preferred_name'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="pronouns">Pronouns:</label>
                            <input type="text" id="pronouns" name="pronouns" 
                                   value="<?= htmlspecialchars($profile['pronouns'] ?? '') ?>" 
                                   placeholder="e.g., she/her, he/him, they/them">
                        </div>
                        
                        <div class="form-group">
                            <label for="major">Major or Area of Study:</label>
                            <input type="text" id="major" name="major" 
                                   value="<?= htmlspecialchars($profile['major'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="goals">Academic or Career Goals:</label>
                            <textarea id="goals" name="goals"><?= htmlspecialchars($profile['goals'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="fun_fact">One Fun Fact About Yourself:</label>
                            <textarea id="fun_fact" name="fun_fact"><?= htmlspecialchars($profile['fun_fact'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="learning_needs">Learning Needs, Accommodations, or Preferences:</label>
                            <textarea id="learning_needs" name="learning_needs"><?= htmlspecialchars($profile['learning_needs'] ?? '') ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn">Save Profile</button>
                        <a href="?page=dashboard" class="btn btn-secondary">Back to Dashboard</a>
                    </form>
                </div>
                <?php
                break;
                
            case 'view_profiles':
                requireRole('teacher');
                
                if (!$courseId) {
                    header('Location: ?page=dashboard');
                    exit;
                }
                
                // Get course info
                $stmt = $db->prepare("SELECT name FROM courses WHERE id = ? AND teacher_id = ?");
                $stmt->execute([$courseId, $_SESSION['user_id']]);
                $course = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$course) {
                    header('Location: ?page=dashboard');
                    exit;
                }
                
                // Get student profiles with search
                $searchQuery = "%$search%";
                $stmt = $db->prepare("
                    SELECT sp.*, u.name, u.email 
                    FROM student_profiles sp 
                    JOIN users u ON sp.user_id = u.id 
                    WHERE sp.course_id = ? AND (
                        u.name LIKE ? OR 
                        u.email LIKE ? OR 
                        sp.preferred_name LIKE ? OR 
                        sp.major LIKE ?
                    )
                    ORDER BY u.name
                ");
                $stmt->execute([$courseId, $searchQuery, $searchQuery, $searchQuery, $searchQuery]);
                $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <h1>Student Profiles - <?= htmlspecialchars($course['name']) ?></h1>
                
                <div class="search-box">
                    <form method="get">
                        <input type="hidden" name="page" value="view_profiles">
                        <input type="hidden" name="course_id" value="<?= $courseId ?>">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Search by name, email, preferred name, or major...">
                        <button type="submit" class="btn">Search</button>
                        <?php if ($search): ?>
                            <a href="?page=view_profiles&course_id=<?= $courseId ?>" class="btn btn-secondary">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <?php if (empty($profiles)): ?>
                    <div class="card">
                        <p>No student profiles found<?= $search ? ' matching your search' : '' ?>.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-3">
                        <?php foreach ($profiles as $profile): ?>
                            <div class="profile-card">
                                <h3><?= htmlspecialchars($profile['name']) ?></h3>
                                <p style="color: #666; margin-bottom: 1rem;"><?= htmlspecialchars($profile['email']) ?></p>
                                
                                <?php if ($profile['preferred_name']): ?>
                                    <div class="profile-field">
                                        <strong>Preferred Name:</strong>
                                        <?= htmlspecialchars($profile['preferred_name']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($profile['pronouns']): ?>
                                    <div class="profile-field">
                                        <strong>Pronouns:</strong>
                                        <?= htmlspecialchars($profile['pronouns']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($profile['major']): ?>
                                    <div class="profile-field">
                                        <strong>Major:</strong>
                                        <?= htmlspecialchars($profile['major']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($profile['goals']): ?>
                                    <div class="profile-field">
                                        <strong>Goals:</strong>
                                        <?= htmlspecialchars($profile['goals']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($profile['fun_fact']): ?>
                                    <div class="profile-field">
                                        <strong>Fun Fact:</strong>
                                        <?= htmlspecialchars($profile['fun_fact']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($profile['learning_needs']): ?>
                                    <div class="profile-field">
                                        <strong>Learning Needs:</strong>
                                        <?= htmlspecialchars($profile['learning_needs']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 2rem;">
                    <a href="?page=dashboard" class="btn btn-secondary">Back to Dashboard</a>
                </div>
                <?php
                break;
                
            default:
                header('Location: ?page=' . (isLoggedIn() ? 'dashboard' : 'login'));
                exit;
        }
        ?>
    </div>
</body>
</html>