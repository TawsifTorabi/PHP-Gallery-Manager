<?php
include 'session.php';

// If the user is not logged in, redirect them to the login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
<!-- Fixed Top Navbar -->
<?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <h2 class="mb-4">Welcome, <?php echo $_SESSION['username']; ?>!</h2>
        <p>You are logged in. This is your dashboard.</p>
        <a href="logout.php" class="btn btn-danger">Logout</a>
        <br>
        <form action="search_gallery.php" method="get">
            <div class="input-group mb-3">
                <input type="text" class="form-control" name="query" placeholder="Search for galleries" required>
                <button class="btn btn-outline-secondary" type="submit">Search</button>
            </div>
        </form>

    </div>

    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

</body>

</html>