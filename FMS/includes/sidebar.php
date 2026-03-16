<?php
requireLogin();
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<aside class="w-64 text-white flex flex-col min-h-screen" style="background-color: #05386D;">
    <div class="p-6 border-b border-indigo-800" style="margin-left: -10px;">
        <div class="flex items-center gap-3">
            <img src="image/costa.png" alt="Costa Cargo Logo" class="w-12 h-12">
            <div>
                <h2 class="text-white font-semibold">COSTA CARGO</h2>
                <p class="text-indigo-300 text-sm">Freight System</p>
            </div>
        </div>
    </div>

    <nav class="flex-1 p-4 space-y-1">
        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentPage == 'dashboard.php' ? 'bg-indigo-800 text-white' : 'text-indigo-200 hover:bg-indigo-800/50'; ?>">
            <i class="fas fa-chart-line w-5"></i>
            <span>Dashboard</span>
        </a>
        <a href="succession-planning.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentPage == 'succession-planning.php' ? 'bg-indigo-800 text-white' : 'text-indigo-200 hover:bg-indigo-800/50'; ?>">
            <i class="fas fa-users w-5"></i>
            <span>Succession Planning</span>
        </a>
        <a href="training-management.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentPage == 'training-management.php' ? 'bg-indigo-800 text-white' : 'text-indigo-200 hover:bg-indigo-800/50'; ?>">
            <i class="fas fa-graduation-cap w-5"></i>
            <span>Training</span>
        </a>
        <a href="competency-management.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentPage == 'competency-management.php' ? 'bg-indigo-800 text-white' : 'text-indigo-200 hover:bg-indigo-800/50'; ?>">
            <i class="fas fa-award w-5"></i>
            <span>Competency</span>
        </a>
        <a href="ess.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentPage == 'ess.php' ? 'bg-indigo-800 text-white' : 'text-indigo-200 hover:bg-indigo-800/50'; ?>">
            <i class="fas fa-user w-5"></i>
            <span>ESS</span>
        </a>
        <a href="learning.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentPage == 'learning.php' ? 'bg-indigo-800 text-white' : 'text-indigo-200 hover:bg-indigo-800/50'; ?>">
            <i class="fas fa-book-open w-5"></i>
            <span>Learning</span>
        </a>

        
        <a href="<?php 
            // Determine if the user is an admin for routing
            $user_id = $_SESSION['user_id'] ?? 0;
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT role, username FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $currUser = $stmt->fetch();
            $roleVal = strtolower($currUser['role'] ?? '');
            $is_admin = ($roleVal === 'admin') || (($currUser['username'] ?? '') === 'admin');
            
            echo $is_admin ? 'payroll-management.php' : 'ess.php?tab=payroll'; 
        ?>" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo ($currentPage == 'payroll-management.php' || ($currentPage == 'ess.php' && isset($_GET['tab']) && $_GET['tab'] == 'payroll')) ? 'bg-indigo-800 text-white' : 'text-indigo-200 hover:bg-indigo-800/50'; ?>">
            <i class="fas fa-file-invoice-dollar w-5"></i>
            <span>Payroll</span>
        </a>
    </nav>

    <div class="p-4 border-t border-indigo-800">
        <div class="flex items-center gap-3 mb-4 px-4">
            <div class="w-10 h-10 bg-indigo-700 rounded-full flex items-center justify-center overflow-hidden">
                <?php 
                $pic = getUserProfilePicture();
                if ($pic): 
                ?>
                    <img src="uploads/avatars/<?php echo htmlspecialchars($pic); ?>" alt="User" class="w-full h-full object-cover">
                <?php else: ?>
                    <i class="fas fa-user text-sm text-white"></i>
                <?php endif; ?>
            </div>
            <div>
                <p class="text-white"><?php echo htmlspecialchars(getUserName()); ?></p>
                <p class="text-indigo-300 text-sm"><?php echo htmlspecialchars(getUserPosition()); ?></p>
            </div>
        </div>
        <a href="logout.php" class="w-full flex items-center gap-3 px-4 py-3 text-indigo-200 hover:bg-indigo-800/50 rounded-lg transition-colors">
            <i class="fas fa-sign-out-alt w-5"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>
