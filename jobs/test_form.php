<?php
// Debug information
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('POST request received in test_form.php: ' . print_r($_POST, true));
    echo '<div style="background-color: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px; border-radius: 4px;">Form submitted successfully! Check the error log for details.</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Form</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background-color: #4a6cf7;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #3d5ed4;
        }
    </style>
</head>
<body>
    <h1>Test Form</h1>
    <p>This is a simple test form to verify that form submission is working.</p>
    
    <form action="" method="post">
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" required>
        </div>
        
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        
        <div class="form-group">
            <label for="message">Message</label>
            <textarea id="message" name="message" rows="5" required></textarea>
        </div>
        
        <button type="submit">Submit</button>
    </form>
    
    <p><a href="/jobs/view.php?id=<?php echo isset($_GET['job_id']) ? $_GET['job_id'] : '1'; ?>">Back to Job View</a></p>
</body>
</html> 