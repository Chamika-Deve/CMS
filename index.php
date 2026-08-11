<?php
session_start();
require_once 'includes/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        
        // Use password_verify since seed data uses bcrypt
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] == 1) {
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ];
                header("Location: pages/dashboard.php");
                exit;
            } else {
                $error = "Your account is disabled.";
            }
        } else {
            $error = "Invalid email or password.";
        }
    } else {
        $error = "Database connection failed. Cannot authenticate.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSMS - Login</title>
    <!-- Tailwind CSS Play CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            900: '#0c4a6e',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background: linear-gradient(135deg, #0c4a6e 0%, #0284c7 100%);
            min-height: 100vh;
        }
        .glass-panel {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
        .input-glow:focus {
            box-shadow: 0 0 15px rgba(14, 165, 233, 0.5);
        }
    </style>
</head>
<body class="flex items-center justify-center p-4 overflow-hidden relative">
    
    <!-- Background Decorators -->
    <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-brand-500 rounded-full mix-blend-multiply filter blur-[128px] opacity-50 animate-float" style="animation-delay: 0s;"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-96 h-96 bg-purple-500 rounded-full mix-blend-multiply filter blur-[128px] opacity-50 animate-float" style="animation-delay: 2s;"></div>

    <div class="w-full max-w-md z-10 relative">
        <div class="text-center mb-8 animate-float" style="animation-delay: 1s;">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl glass-panel mb-4 text-white text-3xl">
                <i class="fa-solid fa-microchip"></i>
            </div>
            <h1 class="text-4xl font-bold text-white tracking-tight">TechShop</h1>
            <p class="text-brand-100 mt-2 font-medium">Management System v2.0</p>
        </div>

        <div class="glass-panel rounded-3xl p-8 sm:p-10 transform transition-all duration-300 hover:scale-[1.02]">
            <h2 class="text-2xl font-semibold text-white mb-6 text-center">Welcome Back</h2>
            
            <?php if ($error): ?>
                <div class="bg-red-500/20 border border-red-500/50 text-red-100 px-4 py-3 rounded-xl mb-6 text-sm flex items-center">
                    <i class="fa-solid fa-circle-exclamation mr-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="index.php" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-brand-50 mb-1" for="email">Email Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-brand-100">
                            <i class="fa-regular fa-envelope"></i>
                        </div>
                        <input type="email" id="email" name="email" required 
                               class="block w-full pl-10 pr-3 py-3 border border-white/20 rounded-xl leading-5 bg-white/10 text-white placeholder-brand-100/50 focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 sm:text-sm transition-colors input-glow" 
                               placeholder="admin@example.com" value="admin@example.com">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-brand-50 mb-1" for="password">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-brand-100">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <input type="password" id="password" name="password" required 
                               class="block w-full pl-10 pr-3 py-3 border border-white/20 rounded-xl leading-5 bg-white/10 text-white placeholder-brand-100/50 focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 sm:text-sm transition-colors input-glow" 
                               placeholder="••••••••" value="password">
                    </div>
                </div>

                <div class="flex items-center justify-between mt-4">
                    <div class="flex items-center">
                        <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 text-brand-600 focus:ring-brand-500 border-gray-300 rounded bg-white/10 border-white/20">
                        <label for="remember-me" class="ml-2 block text-sm text-brand-50">
                            Remember me
                        </label>
                    </div>
                    <div class="text-sm">
                        <a href="#" class="font-medium text-brand-100 hover:text-white transition-colors">Forgot password?</a>
                    </div>
                </div>

                <button type="submit" class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-xl shadow-lg text-sm font-semibold text-brand-900 bg-white hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 transition-all duration-300 hover:shadow-xl mt-6">
                    Sign in to System
                    <i class="fa-solid fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-white/20">
                <p class="text-sm text-brand-100 text-center mb-4">Demo Accounts</p>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <button type="button" onclick="document.getElementById('email').value='admin@example.com'; document.getElementById('password').value='password';" class="bg-white/5 hover:bg-white/10 border border-white/10 rounded-lg p-2 text-white transition-colors text-center">
                        <i class="fa-solid fa-user-shield block mb-1"></i> Admin
                    </button>
                    <button type="button" onclick="document.getElementById('email').value='cashier@example.com'; document.getElementById('password').value='password';" class="bg-white/5 hover:bg-white/10 border border-white/10 rounded-lg p-2 text-white transition-colors text-center">
                        <i class="fa-solid fa-cash-register block mb-1"></i> Cashier
                    </button>
                </div>
            </div>
        </div>
        
        <p class="text-center text-brand-100/60 text-sm mt-8">
            &copy; 2026 TechShop Systems. All rights reserved.
        </p>
    </div>
</body>
</html>
