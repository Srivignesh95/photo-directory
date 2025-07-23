<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3">
  <div class="container">
    <a class="navbar-brand" href="index.php">
      <img src="assets/images/logo.png" alt="St. Timothy Logo" width="220">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
      aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse justify-content-end" id="mainNav">
      <ul class="navbar-nav gap-3">
        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_access'] == 1): ?>
          <li class="nav-item"><a class="nav-link" href="/photo_directory/">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
        <?php elseif (isset($_SESSION['user_id'])): ?>
          <li class="nav-item"><a class="nav-link" href="/photo_directory/">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
