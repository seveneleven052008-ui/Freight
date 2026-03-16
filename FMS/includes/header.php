<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        [x-cloak] { display: none !important; }
        :root {
            --color-indigo-800: #28B4D7;
            --color-indigo-600: #05386D;
        }
        .bg-indigo-800 {
            background-color: #28B4D7 !important;
        }
        .border-indigo-800 {
            border-color: #28B4D7 !important;
        }
        .hover\:bg-indigo-800\/50:hover {
            background-color: rgba(40, 180, 215, 0.5) !important;
        }
        .bg-indigo-600 {
            background-color: #05386D !important;
        }
        .border-indigo-600 {
            border-color: #05386D !important;
        }
        .hover\:bg-indigo-600\/50:hover {
            background-color: rgba(5, 56, 109, 0.5) !important;
        }
    </style>
</head>
<body class="bg-gray-50">
