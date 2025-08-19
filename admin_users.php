<?php
@include 'config.php';
session_start();

$admin_id = $_SESSION['admin_id'] ?? null;
if(!isset($admin_id)){
   header('location:login.php');
   exit;
}

$message = [];

/* ===============================
   Flash message from redirects
================================= */
if(isset($_SESSION['flash_msg']) && $_SESSION['flash_msg'] !== ''){
   $message[] = $_SESSION['flash_msg'];
   unset($_SESSION['flash_msg']);
}

/* ===============================
   Delete user (prevent self-delete)
================================= */
if(isset($_GET['delete'])){
   $delete_id = (int)$_GET['delete'];

   if($delete_id === (int)$admin_id){
      $_SESSION['flash_msg'] = "You can't delete your own admin account.";
      header('location:admin_users.php');
      exit;
   }

   $delete_users = $conn->prepare("DELETE FROM `users` WHERE id = ?");
   $delete_users->execute([$delete_id]);

   $_SESSION['flash_msg'] = 'User account deleted.';
   header('location:admin_users.php');
   exit;
}

/* ===============================
   Stats
================================= */
$number_of_accounts = (int)$conn->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
$number_of_admins   = (int)$conn->query("SELECT COUNT(*) FROM `users` WHERE user_type='admin'")->fetchColumn();
$number_of_users    = (int)$conn->query("SELECT COUNT(*) FROM `users` WHERE user_type='user'")->fetchColumn();

// Optional: messages count if you have `message` table
try {
  $number_of_messages = (int)$conn->query("SELECT COUNT(*) FROM `message`")->fetchColumn();
} catch (Exception $e) {
  $number_of_messages = 0;
}

/* ===============================
   Search & filter
================================= */
function build_users_where(string $q, string $type, array &$params): string {
   $clauses = [];
   if(trim($q) !== ''){
      $like = "%".$q."%";
      $clauses[] = "(name LIKE ? OR email LIKE ? OR CAST(id AS CHAR) LIKE ?)";
      array_push($params, $like, $like, $like);
   }
   if($type !== '' && in_array($type, ['admin','user'], true)){
      $clauses[] = "user_type = ?";
      $params[]  = $type;
   }
   return $clauses ? (' WHERE '.implode(' AND ', $clauses).' ') : '';
}

$q    = isset($_GET['q']) ? trim($_GET['q']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';

$params = [];
$where  = build_users_where($q, $type, $params);

// Count for results label
$stmt_count = $conn->prepare("SELECT COUNT(*) FROM `users`".$where);
$stmt_count->execute($params);
$total_results = (int)$stmt_count->fetchColumn();

/* ===============================
   Fetch users (ordered latest first)
================================= */
$sql = "SELECT * FROM `users`".$where." ORDER BY id DESC";
$select_users = $conn->prepare($sql);
$select_users->execute($params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Admin • Users</title>

   <!-- Tailwind CSS -->
   <script src="https://cdn.tailwindcss.com"></script>

   <!-- Font Awesome -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <style>
     .line-clamp-1{display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden}
     .line-clamp-2{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
   </style>
</head>
<body class="bg-gray-50">
<?php include 'admin_header.php'; ?>

<!-- Main Content -->
<div class="ml-64 pt-16 min-h-screen">
  <div class="p-6">

    <!-- Header -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg p-6 text-white mb-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">User Accounts</h1>
          <p class="text-blue-100"><?= date('l, F j, Y'); ?></p>
        </div>
        <a href="admin_page.php" class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded transition">
          <i class="fas fa-home"></i> Dashboard
        </a>
      </div>
    </div>

    <!-- Messages -->
    <?php if(!empty($message)): ?>
      <div class="mb-4 space-y-2">
        <?php foreach($message as $msg): ?>
          <div class="flex items-center gap-2 bg-blue-50 text-blue-800 border border-blue-200 rounded px-4 py-2">
            <i class="fas fa-info-circle"></i>
            <span><?= htmlspecialchars($msg); ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
          <div class="bg-indigo-100 p-2 rounded">
            <i class="fas fa-users text-indigo-600"></i>
          </div>
          <div class="ml-3">
            <p class="text-sm text-gray-600">Total Users</p>
            <h3 class="text-xl font-bold"><?= $number_of_users; ?></h3>
          </div>
        </div>
        <a href="admin_users.php?type=user" class="text-blue-600 text-sm mt-2 inline-block">Filter Users →</a>
      </div>

      <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
          <div class="bg-red-100 p-2 rounded">
            <i class="fas fa-user-shield text-red-600"></i>
          </div>
          <div class="ml-3">
            <p class="text-sm text-gray-600">Total Admins</p>
            <h3 class="text-xl font-bold"><?= $number_of_admins; ?></h3>
          </div>
        </div>
        <a href="admin_users.php?type=admin" class="text-blue-600 text-sm mt-2 inline-block">Filter Admins →</a>
      </div>

      <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
          <div class="bg-teal-100 p-2 rounded">
            <i class="fas fa-user-friends text-teal-600"></i>
          </div>
          <div class="ml-3">
            <p class="text-sm text-gray-600">All Accounts</p>
            <h3 class="text-xl font-bold"><?= $number_of_accounts; ?></h3>
          </div>
        </div>
        <a href="admin_users.php" class="text-blue-600 text-sm mt-2 inline-block">View All →</a>
      </div>

      <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
          <div class="bg-orange-100 p-2 rounded">
            <i class="fas fa-envelope text-orange-600"></i>
          </div>
          <div class="ml-3">
            <p class="text-sm text-gray-600">Messages</p>
            <h3 class="text-xl font-bold"><?= $number_of_messages; ?></h3>
          </div>
        </div>
        <a href="admin_contacts.php" class="text-blue-600 text-sm mt-2 inline-block">View Messages →</a>
      </div>
    </div>

    <!-- Users Card (Search + Filter + Table) -->
    <div class="bg-white rounded-lg shadow mb-6">
      <div class="p-4 md:p-6 border-b">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
          <div>
            <h3 class="text-lg font-semibold">Manage Accounts</h3>
            <?php if($q !== '' || ($type !== '' && in_array($type, ['admin','user'], true))): ?>
              <p class="text-gray-500 text-sm">
                Showing <span class="font-medium"><?= $total_results; ?></span> result(s)
                <?php if($q !== ''): ?> for “<span class="font-medium"><?= htmlspecialchars($q); ?></span>”<?php endif; ?>
                <?php if($type !== ''): ?> (type: <span class="font-medium"><?= htmlspecialchars($type); ?></span>)<?php endif; ?>
                <a href="admin_users.php" class="text-blue-600 hover:underline ml-2">Reset</a>
              </p>
            <?php else: ?>
              <p class="text-gray-500 text-sm">Search, filter and manage user accounts</p>
            <?php endif; ?>
          </div>

          <form method="GET" class="w-full md:w-auto">
            <div class="flex flex-col sm:flex-row gap-2">
              <div class="relative">
                <i class="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input
                  type="text"
                  name="q"
                  value="<?= htmlspecialchars($q); ?>"
                  placeholder="Search by name, email, or ID…"
                  class="w-full sm:w-72 pl-10 pr-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <select name="type"
                      class="w-full sm:w-40 py-2 px-3 border rounded bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All types</option>
                <option value="admin" <?= $type==='admin'?'selected':''; ?>>Admin</option>
                <option value="user"  <?= $type==='user'?'selected':''; ?>>User</option>
              </select>

              <button class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition">
                <i class="fas fa-filter"></i> Apply
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="p-4 md:p-6 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left border-b">
              <th class="py-2">User</th>
              <th class="py-2">Email</th>
              <th class="py-2">Type</th>
              <th class="py-2">ID</th>
              <th class="py-2 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
              if($select_users->rowCount() > 0){
                while($u = $select_users->fetch(PDO::FETCH_ASSOC)){
                  $uid = (int)$u['id'];
                  $isSelf = ($uid === (int)$admin_id);
                  $img = !empty($u['image']) ? 'uploaded_img/'.$u['image'] : '';
                  $badgeClass = ($u['user_type']==='admin')
                                  ? 'bg-red-100 text-red-700'
                                  : 'bg-indigo-100 text-indigo-700';
            ?>
            <tr class="border-b hover:bg-gray-50">
              <td class="py-2">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-full bg-gray-100 overflow-hidden flex items-center justify-center">
                    <?php if($img && file_exists($img)): ?>
                      <img src="<?= htmlspecialchars($img); ?>" alt="" class="w-full h-full object-cover">
                    <?php else: ?>
                      <i class="fas fa-user text-gray-400"></i>
                    <?php endif; ?>
                  </div>
                  <div>
                    <div class="font-medium line-clamp-1"><?= htmlspecialchars($u['name']); ?></div>
                    <div class="text-xs text-gray-500">Joined ID #<?= $uid; ?></div>
                  </div>
                </div>
              </td>
              <td class="py-2">
                <span class="text-gray-700"><?= htmlspecialchars($u['email']); ?></span>
              </td>
              <td class="py-2">
                <span class="text-xs px-2 py-1 rounded <?= $badgeClass; ?>">
                  <?= htmlspecialchars($u['user_type']); ?>
                </span>
              </td>
              <td class="py-2">
                <span class="text-gray-600"><?= $uid; ?></span>
              </td>
              <td class="py-2">
                <div class="flex items-center justify-end gap-2">
                  <?php if($isSelf): ?>
                    <span class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-600" title="You cannot delete your own account">
                      Current Admin
                    </span>
                  <?php else: ?>
                    <a href="admin_users.php?delete=<?= $uid; ?>"
                       onclick="return confirm('Delete this user?');"
                       class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded transition">
                      <i class="fas fa-trash"></i> Delete
                    </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php
                }
              } else {
                echo '<tr><td colspan="5" class="text-center text-gray-500 py-6">No users found.</td></tr>';
              }
            ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-lg shadow p-6">
      <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <a href="admin_products.php" class="bg-blue-600 hover:bg-blue-700 text-white p-3 rounded text-center transition-colors">
          <i class="fas fa-box mb-2 block"></i>
          <span class="text-sm">Manage Products</span>
        </a>
        <a href="admin_orders.php" class="bg-green-600 hover:bg-green-700 text-white p-3 rounded text-center transition-colors">
          <i class="fas fa-list mb-2 block"></i>
          <span class="text-sm">View Orders</span>
        </a>
        <a href="admin_users.php" class="bg-purple-600 hover:bg-purple-700 text-white p-3 rounded text-center transition-colors">
          <i class="fas fa-users mb-2 block"></i>
          <span class="text-sm">Manage Users</span>
        </a>
        <a href="admin_contacts.php" class="bg-orange-600 hover:bg-orange-700 text-white p-3 rounded text-center transition-colors">
          <i class="fas fa-envelope mb-2 block"></i>
          <span class="text-sm">Messages</span>
        </a>
      </div>
    </div>

  </div>
</div>

<script src="js/script.js"></script>
</body>
</html>
