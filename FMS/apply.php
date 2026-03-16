<?php
require_once 'config/config.php';
// No login required for submitting an application (usually)

$pdo = getDBConnection();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $position = $_POST['position'] ?? '';
    $contact = $_POST['contact'] ?? '';
    
    if ($name && $position && $contact) {
        try {
            $stmt = $pdo->prepare("INSERT INTO applicants (name, position_applied, contact_info, application_status) VALUES (?, ?, ?, 'Pending')");
            $stmt->execute([$name, $position, $contact]);
            $message = "Your application has been submitted successfully! HR will review it soon.";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Error submitting application: " . $e->getMessage();
            $messageType = "error";
        }
    } else {
        $message = "Please fill in all required fields.";
        $messageType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for a Position - Costa Cargo</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f3f4f6; font-family: 'Inter', sans-serif; }
        .brand-color { color: #05386D; }
        .bg-brand { background-color: #05386D; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-white rounded-xl shadow-2xl overflow-hidden">
        <div class="bg-brand p-8 text-center">
            <img src="image/costa.png" alt="Logo" class="w-20 h-20 mx-auto mb-4 bg-white rounded-full p-2">
            <h1 class="text-2xl font-bold text-white uppercase tracking-wider">Join Our Team</h1>
            <p class="text-indigo-200 mt-2">Costa Cargo Freight Management System</p>
        </div>
        
        <div class="p-8">
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg text-sm <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Full Name *</label>
                    <input type="text" name="name" required placeholder="John Doe" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Position Applied For *</label>
                    <select name="position" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                        <option value="">Select a position</option>
                        <option value="Senior Logistics Coordinator">Senior Logistics Coordinator</option>
                        <option value="Fleet Manager">Fleet Manager</option>
                        <option value="Warehouse Supervisor">Warehouse Supervisor</option>
                        <option value="Supply Chain Analyst">Supply Chain Analyst</option>
                        <option value="Customer Support Representative">Customer Support Representative</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Contact Information (Email/Phone) *</label>
                    <input type="text" name="contact" required placeholder="email@example.com or +1234567890" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>
                
                <div class="pt-4">
                    <button type="submit" class="w-full bg-brand text-white font-bold py-3 rounded-lg hover:bg-opacity-90 transform transition-transform hover:scale-105 shadow-lg">
                        Submit Application
                    </button>
                </div>
            </form>
            
            <div class="mt-8 text-center text-gray-500 text-sm">
                <a href="login.php" class="hover:text-indigo-600 font-medium"><i class="fas fa-arrow-left mr-1"></i> Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
