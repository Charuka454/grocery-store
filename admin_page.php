<!-- Quick Actions -->
      <div class="bg-white rounded-lg shadow p-6"><?php

@include 'config.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if(!isset($admin_id)){
   header('location:login.php');
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Admin Dashboard</title>

   <!-- Tailwind CSS -->
   <script src="https://cdn.tailwindcss.com"></script>
   
   <!-- Font Awesome -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   
   <!-- Chart.js -->
   <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>
<body class="bg-gray-50">
   
<?php include 'admin_header.php'; ?>

<!-- Main Content -->
<div class="ml-64 pt-16 min-h-screen">
   <div class="p-6">
      
      <!-- Welcome Header -->
      <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg p-6 text-white mb-6">
         <h1 class="text-2xl font-bold">Admin Dashboard</h1>
         <p class="text-blue-100"><?= date('l, F j, Y'); ?></p>
      </div>

      <!-- Stats Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
         
         <!-- Pending Orders -->
         <div class="bg-white rounded-lg shadow p-4">
            <?php
               $total_pendings = 0;
               $select_pendings = $conn->prepare("SELECT * FROM `orders` WHERE payment_status = ?");
               $select_pendings->execute(['pending']);
               while($fetch_pendings = $select_pendings->fetch(PDO::FETCH_ASSOC)){
                  $total_pendings += $fetch_pendings['total_price'];
               };
            ?>
            <div class="flex items-center">
               <div class="bg-yellow-100 p-2 rounded">
                  <i class="fas fa-clock text-yellow-600"></i>
               </div>
               <div class="ml-3">
                  <p class="text-sm text-gray-600">Total Pendings</p>
                  <h3 class="text-xl font-bold">Rs <?= $total_pendings; ?></h3>
               </div>
            </div>
            <a href="admin_orders.php" class="text-blue-600 text-sm mt-2 inline-block">View Orders →</a>
         </div>

         <!-- Completed Orders -->
         <div class="bg-white rounded-lg shadow p-4">
            <?php
               $total_completed = 0;
               $select_completed = $conn->prepare("SELECT * FROM `orders` WHERE payment_status = ?");
               $select_completed->execute(['completed']);
               while($fetch_completed = $select_completed->fetch(PDO::FETCH_ASSOC)){
                  $total_completed += $fetch_completed['total_price'];
               };
            ?>
            <div class="flex items-center">
               <div class="bg-green-100 p-2 rounded">
                  <i class="fas fa-check text-green-600"></i>
               </div>
               <div class="ml-3">
                  <p class="text-sm text-gray-600">Completed Orders</p>
                  <h3 class="text-xl font-bold">Rs <?= $total_completed; ?></h3>
               </div>
            </div>
            <a href="admin_orders.php" class="text-blue-600 text-sm mt-2 inline-block">View Orders →</a>
         </div>

         <!-- Total Orders -->
         <div class="bg-white rounded-lg shadow p-4">
            <?php
               $select_orders = $conn->prepare("SELECT * FROM `orders`");
               $select_orders->execute();
               $number_of_orders = $select_orders->rowCount();
            ?>
            <div class="flex items-center">
               <div class="bg-blue-100 p-2 rounded">
                  <i class="fas fa-shopping-cart text-blue-600"></i>
               </div>
               <div class="ml-3">
                  <p class="text-sm text-gray-600">Orders Placed</p>
                  <h3 class="text-xl font-bold"><?= $number_of_orders; ?></h3>
               </div>
            </div>
            <a href="admin_orders.php" class="text-blue-600 text-sm mt-2 inline-block">View Orders →</a>
         </div>

         <!-- Total Products -->
         <div class="bg-white rounded-lg shadow p-4">
            <?php
               $select_products = $conn->prepare("SELECT * FROM `products`");
               $select_products->execute();
               $number_of_products = $select_products->rowCount();
            ?>
            <div class="flex items-center">
               <div class="bg-purple-100 p-2 rounded">
                  <i class="fas fa-box text-purple-600"></i>
               </div>
               <div class="ml-3">
                  <p class="text-sm text-gray-600">Products Added</p>
                  <h3 class="text-xl font-bold"><?= $number_of_products; ?></h3>
               </div>
            </div>
            <a href="admin_products.php" class="text-blue-600 text-sm mt-2 inline-block">View Products →</a>
         </div>
      </div>

      <!-- Second Row Stats -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
         
         <!-- Total Users -->
         <div class="bg-white rounded-lg shadow p-4">
            <?php
               $select_users = $conn->prepare("SELECT * FROM `users` WHERE user_type = ?");
               $select_users->execute(['user']);
               $number_of_users = $select_users->rowCount();
            ?>
            <div class="flex items-center">
               <div class="bg-indigo-100 p-2 rounded">
                  <i class="fas fa-users text-indigo-600"></i>
               </div>
               <div class="ml-3">
                  <p class="text-sm text-gray-600">Total Users</p>
                  <h3 class="text-xl font-bold"><?= $number_of_users; ?></h3>
               </div>
            </div>
            <a href="admin_users.php" class="text-blue-600 text-sm mt-2 inline-block">View Users →</a>
         </div>

         <!-- Total Admins -->
         <div class="bg-white rounded-lg shadow p-4">
            <?php
               $select_admins = $conn->prepare("SELECT * FROM `users` WHERE user_type = ?");
               $select_admins->execute(['admin']);
               $number_of_admins = $select_admins->rowCount();
            ?>
            <div class="flex items-center">
               <div class="bg-red-100 p-2 rounded">
                  <i class="fas fa-user-shield text-red-600"></i>
               </div>
               <div class="ml-3">
                  <p class="text-sm text-gray-600">Total Admins</p>
                  <h3 class="text-xl font-bold"><?= $number_of_admins; ?></h3>
               </div>
            </div>
            <a href="admin_users.php" class="text-blue-600 text-sm mt-2 inline-block">View Accounts →</a>
         </div>

         <!-- Total Accounts -->
         <div class="bg-white rounded-lg shadow p-4">
            <?php
               $select_accounts = $conn->prepare("SELECT * FROM `users`");
               $select_accounts->execute();
               $number_of_accounts = $select_accounts->rowCount();
            ?>
            <div class="flex items-center">
               <div class="bg-teal-100 p-2 rounded">
                  <i class="fas fa-user-friends text-teal-600"></i>
               </div>
               <div class="ml-3">
                  <p class="text-sm text-gray-600">Total Accounts</p>
                  <h3 class="text-xl font-bold"><?= $number_of_accounts; ?></h3>
               </div>
            </div>
            <a href="admin_users.php" class="text-blue-600 text-sm mt-2 inline-block">View Accounts →</a>
         </div>

         <!-- Total Messages -->
         <div class="bg-white rounded-lg shadow p-4">
            <?php
               $select_messages = $conn->prepare("SELECT * FROM `message`");
               $select_messages->execute();
               $number_of_messages = $select_messages->rowCount();
            ?>
            <div class="flex items-center">
               <div class="bg-orange-100 p-2 rounded">
                  <i class="fas fa-envelope text-orange-600"></i>
               </div>
               <div class="ml-3">
                  <p class="text-sm text-gray-600">Total Messages</p>
                  <h3 class="text-xl font-bold"><?= $number_of_messages; ?></h3>
               </div>
            </div>
            <a href="admin_contacts.php" class="text-blue-600 text-sm mt-2 inline-block">View Messages →</a>
         </div>
      </div>

      <!-- Analytics Section -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
         
         <!-- Order Status Chart -->
         <div class="bg-white rounded-lg shadow p-4">
            <h3 class="text-lg font-semibold mb-4">Order Status</h3>
            <div style="height: 200px;">
               <canvas id="orderChart"></canvas>
            </div>
         </div>

         <!-- Orders by Date Chart -->
         <div class="bg-white rounded-lg shadow p-4 lg:col-span-2">
            <h3 class="text-lg font-semibold mb-4">Orders Analysis (Last 7 Days)</h3>
            <div style="height: 200px;">
               <canvas id="dateChart"></canvas>
            </div>
         </div>

         <!-- Recent Orders -->
         <div class="bg-white rounded-lg shadow p-4">
            <div class="flex justify-between items-center mb-4">
               <h3 class="text-lg font-semibold">Recent Orders</h3>
               <a href="admin_orders.php" class="text-blue-600 text-sm">View All</a>
            </div>
            
            <div class="space-y-2">
               <?php
                  $select_recent = $conn->prepare("SELECT * FROM `orders` ORDER BY placed_on DESC LIMIT 5");
                  $select_recent->execute();
                  if($select_recent->rowCount() > 0){
                     while($recent = $select_recent->fetch(PDO::FETCH_ASSOC)){
               ?>
               <div class="flex justify-between items-center p-2 border-b">
                  <div>
                     <p class="font-medium text-sm"><?= $recent['name']; ?></p>
                     <p class="text-xs text-gray-500"><?= $recent['placed_on']; ?></p>
                  </div>
                  <div class="text-right">
                     <p class="font-semibold text-sm">Rs <?= $recent['total_price']; ?></p>
                     <span class="text-xs px-2 py-1 rounded <?= $recent['payment_status'] == 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                        <?= ucfirst($recent['payment_status']); ?>
                     </span>
                  </div>
               </div>
               <?php 
                     }
                  } else {
                     echo '<p class="text-gray-500 text-center py-4">No orders found</p>';
                  }
               ?>
            </div>
         </div>
      </div>

      <!-- Recent Orders Table -->
      <div class="bg-white rounded-lg shadow p-4 mb-6">
         <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Recent Orders</h3>
            <a href="admin_orders.php" class="text-blue-600 text-sm">View All →</a>
         </div>
         
         <div class="overflow-x-auto">
            <table class="w-full text-sm">
               <thead>
                  <tr class="border-b">
                     <th class="text-left py-2">Customer</th>
                     <th class="text-left py-2">Date</th>
                     <th class="text-left py-2">Amount</th>
                     <th class="text-left py-2">Status</th>
                  </tr>
               </thead>
               <tbody>
                  <?php
                     $select_recent = $conn->prepare("SELECT * FROM `orders` ORDER BY placed_on DESC LIMIT 6");
                     $select_recent->execute();
                     if($select_recent->rowCount() > 0){
                        while($recent = $select_recent->fetch(PDO::FETCH_ASSOC)){
                  ?>
                  <tr class="border-b hover:bg-gray-50">
                     <td class="py-2"><?= $recent['name']; ?></td>
                     <td class="py-2 text-gray-600"><?= date('M j, Y', strtotime($recent['placed_on'])); ?></td>
                     <td class="py-2 font-semibold">Rs <?= $recent['total_price']; ?></td>
                     <td class="py-2">
                        <span class="text-xs px-2 py-1 rounded <?= $recent['payment_status'] == 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                           <?= ucfirst($recent['payment_status']); ?>
                        </span>
                     </td>
                  </tr>
                  <?php 
                        }
                     } else {
                        echo '<tr><td colspan="4" class="text-gray-500 text-center py-4">No orders found</td></tr>';
                     }
                  ?>
               </tbody>
            </table>
         </div>
      </div>
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

   </div>
</div>

<script>
// Get order data for last 7 days
<?php
   // Prepare data for the last 7 days
   $dates = [];
   $order_counts = [];
   $revenue_data = [];
   
   for ($i = 6; $i >= 0; $i--) {
       $date = date('Y-m-d', strtotime("-$i days"));
       $display_date = date('M j', strtotime("-$i days"));
       $dates[] = $display_date;
       
       // Count orders for this date
       $count_query = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_price), 0) as revenue FROM `orders` WHERE DATE(placed_on) = ?");
       $count_query->execute([$date]);
       $result = $count_query->fetch(PDO::FETCH_ASSOC);
       
       $order_counts[] = $result['count'] ?: 0;
       $revenue_data[] = $result['revenue'] ?: 0;
   }
   
   // Get order status counts
   $completed_query = $conn->prepare("SELECT COUNT(*) as count FROM `orders` WHERE payment_status = 'completed'");
   $completed_query->execute();
   $completed_count = $completed_query->fetch(PDO::FETCH_ASSOC)['count'] ?: 0;
   
   $pending_query = $conn->prepare("SELECT COUNT(*) as count FROM `orders` WHERE payment_status = 'pending'");
   $pending_query->execute();
   $pending_count = $pending_query->fetch(PDO::FETCH_ASSOC)['count'] ?: 0;
?>

// Order Status Chart
const statusCtx = document.getElementById('orderChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Completed', 'Pending'],
        datasets: [{
            data: [<?= $completed_count; ?>, <?= $pending_count; ?>],
            backgroundColor: ['#10B981', '#F59E0B'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    usePointStyle: true
                }
            }
        }
    }
});

// Orders by Date Chart
const dateCtx = document.getElementById('dateChart').getContext('2d');
new Chart(dateCtx, {
    type: 'line',
    data: {
        labels: [<?php echo '"' . implode('","', $dates) . '"'; ?>],
        datasets: [{
            label: 'Orders',
            data: [<?php echo implode(',', $order_counts); ?>],
            borderColor: '#3B82F6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            fill: true,
            yAxisID: 'y'
        }, {
            label: 'Revenue (Rs)',
            data: [<?php echo implode(',', $revenue_data); ?>],
            borderColor: '#10B981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.4,
            fill: true,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    usePointStyle: true,
                    padding: 20
                }
            }
        },
        scales: {
            x: {
                display: true,
                grid: {
                    display: false
                }
            },
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Number of Orders'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Revenue (Rs)'
                },
                grid: {
                    drawOnChartArea: false,
                }
            }
        }
    }
});
</script>

</body>
</html>
