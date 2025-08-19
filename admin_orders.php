<?php

@include 'config.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if(!isset($admin_id)){
   header('location:login.php');
   exit;
}

if(isset($_POST['update_order'])){
   $order_id = $_POST['order_id'];
   $update_payment = $_POST['update_payment'] ?? '';
   $update_payment = filter_var($update_payment, FILTER_SANITIZE_STRING);

   if($update_payment){
      $update_orders = $conn->prepare("UPDATE `orders` SET payment_status = ? WHERE id = ?");
      $update_orders->execute([$update_payment, $order_id]);
      $message[] = 'Payment has been updated!';
   }else{
      $message[] = 'Please select a payment status before updating.';
   }
}

if(isset($_GET['delete'])){
   $delete_id = $_GET['delete'];
   $delete_orders = $conn->prepare("DELETE FROM `orders` WHERE id = ?");
   $delete_orders->execute([$delete_id]);
   header('location:admin_orders.php');
   exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8" />
   <meta http-equiv="X-UA-Compatible" content="IE=edge" />
   <meta name="viewport" content="width=device-width, initial-scale=1.0" />
   <title>Orders</title>

   <!-- Tailwind CSS -->
   <script src="https://cdn.tailwindcss.com"></script>

   <!-- Font Awesome -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css" />

   <!-- (Optional) Keep your existing css if needed elsewhere -->
   <!-- <link rel="stylesheet" href="css/admin_style.css"> -->

   <style>
      /* Hide default select arrow for better Tailwind styling on some browsers */
      .no-native-arrow {
         -moz-appearance: none;
         -webkit-appearance: none;
         appearance: none;
         background-position: right 0.5rem center;
         background-repeat: no-repeat;
      }
   </style>
</head>
<body class="bg-gray-50">
   
<?php include 'admin_header.php'; ?>

<!-- Main Content wrapper matching your dashboard offset -->
<div class="ml-64 pt-16 min-h-screen">
  <div class="p-6">

    <!-- Gradient Header -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg p-6 text-white mb-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">Placed Orders</h1>
          <p class="text-blue-100"><?= date('l, F j, Y'); ?></p>
        </div>
        <a href="admin_page.php" class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded transition">
          <i class="fas fa-home"></i> Dashboard
        </a>
      </div>
    </div>

    <!-- Messages (uses your $message[] pattern shown in other files) -->
    <?php if(!empty($message)): ?>
      <div class="mb-4 space-y-2">
        <?php foreach($message as $msg): ?>
          <div class="flex items-center gap-2 bg-green-50 text-green-700 border border-green-200 rounded px-4 py-2">
            <i class="fas fa-check-circle"></i>
            <span><?= htmlspecialchars($msg); ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Quick Actions (to match your dashboard block) -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
      <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <a href="admin_products.php" class="bg-blue-600 hover:bg-blue-700 text-white p-3 rounded text-center transition-colors">
          <i class="fas fa-plus mb-2 block"></i>
          <span class="text-sm">Add Product</span>
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

    <!-- Orders Table Card -->
    <div class="bg-white rounded-lg shadow">
      <div class="p-4 md:p-6 border-b">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
          <h3 class="text-lg font-semibold">All Orders</h3>
          <div class="text-sm text-gray-500">
            Manage payments, view details, or delete orders.
          </div>
        </div>
      </div>

      <div class="p-4 md:p-6 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-gray-600 border-b">
              <th class="py-3 pr-4">User ID</th>
              <th class="py-3 pr-4">Placed On</th>
              <th class="py-3 pr-4">Customer</th>
              <th class="py-3 pr-4">Email / Number</th>
              <th class="py-3 pr-4">Address</th>
              <th class="py-3 pr-4">Products</th>
              <th class="py-3 pr-4">Total</th>
              <th class="py-3 pr-4">Method</th>
              <th class="py-3 pr-4">Status</th>
              <th class="py-3 pr-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php
            $select_orders = $conn->prepare("SELECT * FROM `orders` ORDER BY placed_on DESC");
            $select_orders->execute();
            if($select_orders->rowCount() > 0):
              while($fetch_orders = $select_orders->fetch(PDO::FETCH_ASSOC)):
          ?>
            <tr class="border-b hover:bg-gray-50 align-top">
              <td class="py-3 pr-4 font-medium text-gray-800"><?= $fetch_orders['user_id']; ?></td>
              <td class="py-3 pr-4 text-gray-600"><?= htmlspecialchars($fetch_orders['placed_on']); ?></td>
              <td class="py-3 pr-4">
                <div class="font-medium"><?= htmlspecialchars($fetch_orders['name']); ?></div>
              </td>
              <td class="py-3 pr-4">
                <div class="text-gray-700"><?= htmlspecialchars($fetch_orders['email']); ?></div>
                <div class="text-gray-500 text-xs"><?= htmlspecialchars($fetch_orders['number']); ?></div>
              </td>
              <td class="py-3 pr-4">
                <div class="max-w-xs truncate" title="<?= htmlspecialchars($fetch_orders['address']); ?>">
                  <?= htmlspecialchars($fetch_orders['address']); ?>
                </div>
              </td>
              <td class="py-3 pr-4">
                <div class="max-w-xs truncate" title="<?= htmlspecialchars($fetch_orders['total_products']); ?>">
                  <?= htmlspecialchars($fetch_orders['total_products']); ?>
                </div>
              </td>
              <td class="py-3 pr-4 font-semibold">Rs.<?= htmlspecialchars($fetch_orders['total_price']); ?></td>
              <td class="py-3 pr-4"><?= htmlspecialchars($fetch_orders['method']); ?></td>
              <td class="py-3 pr-4">
                <?php
                  $status = strtolower($fetch_orders['payment_status']);
                  $badgeClass = $status === 'completed'
                    ? 'bg-green-100 text-green-800'
                    : ($status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800');
                ?>
                <span class="text-xs px-2 py-1 rounded <?= $badgeClass; ?>">
                  <?= ucfirst(htmlspecialchars($fetch_orders['payment_status'])); ?>
                </span>
              </td>
              <td class="py-3 pr-0">
                <form action="" method="POST" class="flex items-center gap-2 justify-end">
                  <input type="hidden" name="order_id" value="<?= $fetch_orders['id']; ?>">
                  <div class="relative">
                    <select name="update_payment"
                            class="no-native-arrow border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white pr-8"
                            style="background-image:url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 20%22 fill=%22%236b7280%22><path fill-rule=%22evenodd%22 d=%22M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.25 4.38a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z%22 clip-rule=%22evenodd%22 /></svg>');">
                      <option value="" selected disabled><?= htmlspecialchars($fetch_orders['payment_status']); ?></option>
                      <option value="pending">pending</option>
                      <option value="completed">completed</option>
                    </select>
                  </div>
                  <button type="submit" name="update_order"
                          class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-3 py-2 rounded transition">
                    <i class="fas fa-save"></i> update
                  </button>
                  <a href="admin_orders.php?delete=<?= $fetch_orders['id']; ?>"
                     onclick="return confirm('Delete this order?');"
                     class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-3 py-2 rounded transition">
                    <i class="fas fa-trash"></i> delete
                  </a>
                </form>
              </td>
            </tr>
          <?php
              endwhile;
            else:
          ?>
            <tr>
              <td colspan="10" class="text-center text-gray-500 py-8">No orders placed yet!</td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- (Optional) Your existing JS -->
<script src="js/script.js"></script>

</body>
</html>
