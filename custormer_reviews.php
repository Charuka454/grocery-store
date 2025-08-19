<?php
// custormer_reviews.php

@include 'config.php';
session_start();

$admin_id = $_SESSION['admin_id'] ?? null;
if(!isset($admin_id)){
   header('location:login.php');
   exit;
}

$message = [];

/* ===============================
   Flash message (from redirects)
================================= */
if(isset($_SESSION['flash_msg']) && $_SESSION['flash_msg'] !== ''){
   $message[] = $_SESSION['flash_msg'];
   unset($_SESSION['flash_msg']);
}

/* ===============================
   Delete review (and image file)
================================= */
if(isset($_GET['delete'])){
   $delete_id = (int)$_GET['delete'];

   // remove image if any
   $stmt = $conn->prepare("SELECT image_path FROM `reviews` WHERE id = ?");
   $stmt->execute([$delete_id]);
   if($stmt->rowCount()){
      $r = $stmt->fetch(PDO::FETCH_ASSOC);
      if(!empty($r['image_path']) && file_exists($r['image_path'])){
         @unlink($r['image_path']);
      }
   }

   $del = $conn->prepare("DELETE FROM `reviews` WHERE id = ?");
   $del->execute([$delete_id]);

   $_SESSION['flash_msg'] = 'Review deleted.';
   header('location:custormer_reviews.php');
   exit;
}

/* ===============================
   Stats
================================= */
$total_reviews   = (int)$conn->query("SELECT COUNT(*) FROM `reviews`")->fetchColumn();
$total_approved  = (int)$conn->query("SELECT COUNT(*) FROM `reviews` WHERE status='approved'")->fetchColumn();
$total_pending   = (int)$conn->query("SELECT COUNT(*) FROM `reviews` WHERE status='pending'")->fetchColumn();
$total_rejected  = (int)$conn->query("SELECT COUNT(*) FROM `reviews` WHERE status='rejected'")->fetchColumn();

/* ===============================
   Search + Filter
   - q: name/email/title/message/order_id
   - status: approved|pending|rejected
================================= */
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

$params = [];
$where = [];

if($q !== ''){
   $like = "%{$q}%";
   $where[] = "(name LIKE ? OR email LIKE ? OR title LIKE ? OR message LIKE ? OR order_id LIKE ?)";
   array_push($params, $like, $like, $like, $like, $like);
}
if(in_array($status, ['approved','pending','rejected'], true)){
   $where[] = "status = ?";
   $params[] = $status;
}

$whereSql = $where ? (' WHERE '.implode(' AND ', $where).' ') : '';

/* ===============================
   Count results and fetch list
================================= */
$stmt_count = $conn->prepare("SELECT COUNT(*) FROM `reviews`".$whereSql);
$stmt_count->execute($params);
$total_results = (int)$stmt_count->fetchColumn();

$sql = "SELECT * FROM `reviews`".$whereSql." ORDER BY created_at DESC";
$rows = $conn->prepare($sql);
$rows->execute($params);

function stars_html($rating){
   $rating = (int)$rating;
   $html = '';
   for($i=1;$i<=5;$i++){
      if($i <= $rating){
         $html .= '<i class="fa-solid fa-star"></i>';
      }else{
         $html .= '<i class="fa-regular fa-star"></i>';
      }
   }
   return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8" />
   <meta http-equiv="X-UA-Compatible" content="IE=edge" />
   <meta name="viewport" content="width=device-width, initial-scale=1.0" />
   <title>Admin • Customer Reviews</title>

   <!-- Tailwind CSS -->
   <script src="https://cdn.tailwindcss.com"></script>

   <!-- Font Awesome -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>

   <style>
     .line-clamp-1{display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden}
     .line-clamp-2{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
     .line-clamp-4{display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden}
     .rating i{ color:#F59E0B; margin-right:2px; }
   </style>
</head>
<body class="bg-gray-50">
<?php include 'admin_header.php'; ?>

<!-- Main wrapper (align with your dashboard offset) -->
<div class="ml-64 pt-16 min-h-screen">
  <div class="p-6">

    <!-- Gradient Header -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg p-6 text-white mb-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">Customer Reviews</h1>
          <p class="text-blue-100"><?= date('l, F j, Y'); ?></p>
        </div>
        <a href="admin_page.php" class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded transition">
          <i class="fas fa-home"></i> Dashboard
        </a>
      </div>
    </div>

    <!-- Flash Messages -->
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
            <i class="fas fa-comments text-indigo-600"></i>
          </div>
          <div class="ml-3">
            <p class="text-sm text-gray-600">Total Reviews</p>
            <h3 class="text-xl font-bold"><?= $total_reviews; ?></h3>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
          <div class="bg-green-100 p-2 rounded">
            <i class="fas fa-check text-green-600"></i>
          </div>
          <div class="ml-3">
            <p class="text-sm text-gray-600">Approved</p>
            <h3 class="text-xl font-bold"><?= $total_approved; ?></h3>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
          <div class="bg-yellow-100 p-2 rounded">
            <i class="fas fa-hourglass-half text-yellow-600"></i>
          </div>
          <div class="ml-3">
            <p class="text-sm text-gray-600">Pending</p>
            <h3 class="text-xl font-bold"><?= $total_pending; ?></h3>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
          <div class="bg-red-100 p-2 rounded">
            <i class="fas fa-times text-red-600"></i>
          </div>
          <div class="ml-3">
            <p class="text-sm text-gray-600">Rejected</p>
            <h3 class="text-xl font-bold"><?= $total_rejected; ?></h3>
          </div>
        </div>
      </div>
    </div>

    <!-- Reviews Manager -->
    <div class="bg-white rounded-lg shadow mb-6">
      <!-- Toolbar -->
      <div class="p-4 md:p-6 border-b">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
          <div>
            <h3 class="text-lg font-semibold">All Reviews</h3>
            <?php if($q !== '' || $status !== ''): ?>
              <p class="text-gray-500 text-sm">
                Showing <span class="font-medium"><?= $total_results; ?></span> result(s)
                <?php if($q !== ''): ?> for “<span class="font-medium"><?= htmlspecialchars($q); ?></span>”<?php endif; ?>
                <?php if($status !== ''): ?> (<?= htmlspecialchars(ucfirst($status)); ?>)<?php endif; ?>
                <a href="custormer_reviews.php" class="text-blue-600 hover:underline ml-2">Reset</a>
              </p>
            <?php else: ?>
              <p class="text-gray-500 text-sm">Search, filter and manage customer reviews</p>
            <?php endif; ?>
          </div>

          <!-- Search/Filter Form -->
          <form method="GET" class="w-full md:w-auto">
            <div class="flex flex-col sm:flex-row gap-2">
              <div class="relative">
                <i class="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input
                  type="text"
                  name="q"
                  value="<?= htmlspecialchars($q); ?>"
                  placeholder="Search name, email, title, message, order ID…"
                  class="w-full sm:w-80 pl-10 pr-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
              <select name="status" class="w-full sm:w-44 py-2 px-3 border rounded bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Status</option>
                <option value="approved" <?= $status==='approved'?'selected':''; ?>>Approved</option>
                <option value="pending"  <?= $status==='pending'?'selected':''; ?>>Pending</option>
                <option value="rejected" <?= $status==='rejected'?'selected':''; ?>>Rejected</option>
              </select>
              <button class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition">
                <i class="fas fa-filter"></i> Apply
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Table -->
      <div class="p-4 md:p-6 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left border-b">
              <th class="py-2">Reviewer</th>
              <th class="py-2">Email</th>
              <th class="py-2">Rating</th>
              <th class="py-2">Title</th>
              <th class="py-2">Message</th>
              <th class="py-2">Status</th>
              <th class="py-2">Date</th>
              <th class="py-2 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            if($rows->rowCount() > 0){
              while($r = $rows->fetch(PDO::FETCH_ASSOC)){
                $rid = (int)$r['id'];
                $badgeClass = 'bg-gray-100 text-gray-700';
                if($r['status']==='approved') $badgeClass = 'bg-green-100 text-green-700';
                elseif($r['status']==='pending') $badgeClass = 'bg-yellow-100 text-yellow-700';
                elseif($r['status']==='rejected') $badgeClass = 'bg-red-100 text-red-700';
            ?>
            <tr class="border-b hover:bg-gray-50 align-top">
              <td class="py-2">
                <div class="flex items-start gap-3">
                  <?php if(!empty($r['image_path']) && file_exists($r['image_path'])): ?>
                    <img src="<?= htmlspecialchars($r['image_path']); ?>" alt="" class="w-10 h-10 rounded object-cover border" />
                  <?php else: ?>
                    <div class="w-10 h-10 rounded bg-gray-100 flex items-center justify-center">
                      <i class="fas fa-user text-gray-400"></i>
                    </div>
                  <?php endif; ?>
                  <div>
                    <div class="font-medium line-clamp-1"><?= htmlspecialchars($r['name']); ?></div>
                    <?php if(!empty($r['user_id'])): ?>
                      <div class="text-xs text-gray-500">User ID #<?= (int)$r['user_id']; ?></div>
                    <?php endif; ?>
                    <?php if(!empty($r['order_id'])): ?>
                      <div class="text-xs text-gray-500">Order #<?= htmlspecialchars($r['order_id']); ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td class="py-2">
                <a href="mailto:<?= htmlspecialchars($r['email']); ?>" class="text-blue-600 hover:underline">
                  <?= htmlspecialchars($r['email']); ?>
                </a>
              </td>
              <td class="py-2">
                <div class="rating"><?= stars_html($r['rating']); ?></div>
              </td>
              <td class="py-2">
                <div class="font-medium line-clamp-2" title="<?= htmlspecialchars($r['title']); ?>">
                  <?= htmlspecialchars($r['title']); ?>
                </div>
              </td>
              <td class="py-2">
                <div class="text-gray-700 line-clamp-4" title="<?= htmlspecialchars($r['message']); ?>">
                  <?= nl2br(htmlspecialchars($r['message'])); ?>
                </div>
              </td>
              <td class="py-2">
                <span class="text-xs px-2 py-1 rounded <?= $badgeClass; ?>">
                  <?= htmlspecialchars(ucfirst($r['status'])); ?>
                </span>
              </td>
              <td class="py-2 text-gray-600">
                <?= date('M j, Y H:i', strtotime($r['created_at'])); ?>
              </td>
              <td class="py-2">
                <div class="flex items-center justify-end gap-2">
                  <a href="custormer_reviews.php?delete=<?= $rid; ?>"
                     onclick="return confirm('Delete this review?');"
                     class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded transition">
                     <i class="fas fa-trash"></i> Delete
                  </a>
                </div>
              </td>
            </tr>
            <?php
              }
            } else {
              echo '<tr><td colspan="8" class="text-center text-gray-500 py-6">No reviews found.</td></tr>';
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
