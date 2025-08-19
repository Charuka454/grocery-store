<?php

if(isset($message)){
   foreach($message as $message){
      echo '
      <div class="fixed top-4 right-4 z-50 bg-red-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center space-x-2">
         <span>'.$message.'</span>
         <i class="fas fa-times cursor-pointer hover:bg-red-600 p-1 rounded" onclick="this.parentElement.remove();"></i>
      </div>
      ';
   }
}

?>

<!-- Sidebar -->
<div class="fixed inset-y-0 left-0 z-50 w-64 bg-gradient-to-b from-slate-900 to-slate-800 shadow-xl">
   <!-- Logo -->
   <div class="flex items-center justify-center h-20 border-b border-slate-700">
      <a href="admin_page.php" class="text-2xl font-bold text-white">
         Admin<span class="text-blue-400">Panel</span>
      </a>
   </div>

   <!-- Navigation -->
   <nav class="mt-8">
      <div class="px-4 space-y-2">
         <a href="admin_page.php" class="flex items-center px-4 py-3 text-gray-300 rounded-lg hover:bg-slate-700 hover:text-white transition-all duration-200 group">
            <i class="fas fa-home mr-3 text-blue-400 group-hover:text-blue-300"></i>
            <span class="font-medium">Dashboard</span>
         </a>
         
         <a href="admin_products.php" class="flex items-center px-4 py-3 text-gray-300 rounded-lg hover:bg-slate-700 hover:text-white transition-all duration-200 group">
            <i class="fas fa-box mr-3 text-green-400 group-hover:text-green-300"></i>
            <span class="font-medium">Products</span>
         </a>
         
         <a href="admin_orders.php" class="flex items-center px-4 py-3 text-gray-300 rounded-lg hover:bg-slate-700 hover:text-white transition-all duration-200 group">
            <i class="fas fa-shopping-cart mr-3 text-purple-400 group-hover:text-purple-300"></i>
            <span class="font-medium">Orders</span>
         </a>
         
         <a href="admin_users.php" class="flex items-center px-4 py-3 text-gray-300 rounded-lg hover:bg-slate-700 hover:text-white transition-all duration-200 group">
            <i class="fas fa-users mr-3 text-orange-400 group-hover:text-orange-300"></i>
            <span class="font-medium">Users</span>
         </a>
         
         <a href="admin_contacts.php" class="flex items-center px-4 py-3 text-gray-300 rounded-lg hover:bg-slate-700 hover:text-white transition-all duration-200 group">
            <i class="fas fa-envelope mr-3 text-red-400 group-hover:text-red-300"></i>
            <span class="font-medium">Messages</span>
         </a>
         <a href="custormer_reviews.php" class="flex items-center px-4 py-3 text-gray-300 rounded-lg hover:bg-slate-700 hover:text-white transition-all duration-200 group">
            <i class="fas fa-star-half-stroke mr-3 text-yellow-400 group-hover:text-yellow-300"></i>
            <span class="font-medium">custormer reviews</span>
         </a>

      </div>
   </nav>

   <!-- User Profile Section -->
   <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-slate-700">
      <?php
         $select_profile = $conn->prepare("SELECT * FROM `users` WHERE id = ?");
         $select_profile->execute([$admin_id]);
         $fetch_profile = $select_profile->fetch(PDO::FETCH_ASSOC);
      ?>
      
      <div class="flex items-center space-x-3 mb-4">
         <img src="uploaded_img/<?= $fetch_profile['image']; ?>" alt="Profile" class="w-10 h-10 rounded-full border-2 border-slate-600">
         <div class="flex-1 min-w-0">
            <p class="text-white font-medium text-sm truncate"><?= $fetch_profile['name']; ?></p>
            <p class="text-slate-400 text-xs">Administrator</p>
         </div>
      </div>
      
      <div class="space-y-2">
         <a href="admin_update_profile.php" class="block w-full text-center py-2 px-4 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors duration-200">
            Update Profile
         </a>
         <a href="logout.php" class="block w-full text-center py-2 px-4 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors duration-200">
            Logout
         </a>
      </div>
   </div>
</div>

<!-- Top Header Bar -->
<header class="fixed top-0 left-64 right-0 h-16 bg-white shadow-sm border-b border-gray-200 z-40">
   <div class="flex items-center justify-between h-full px-6">
      <div class="flex items-center space-x-4">
         <button id="sidebar-toggle" class="lg:hidden p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100">
            <i class="fas fa-bars text-lg"></i>
         </button>
         <h1 class="text-xl font-semibold text-gray-800">Dashboard</h1>
      </div>
      
      <div class="flex items-center space-x-4">
         <div class="relative">
            <button class="p-2 rounded-full text-gray-600 hover:text-gray-900 hover:bg-gray-100 relative">
               <i class="fas fa-bell text-lg"></i>
               <span class="absolute -top-1 -right-1 h-4 w-4 bg-red-500 rounded-full text-xs text-white flex items-center justify-center">3</span>
            </button>
         </div>
         
         <div class="relative">
            <button class="flex items-center space-x-2 p-2 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-gray-100">
               <img src="uploaded_img/<?= $fetch_profile['image']; ?>" alt="Profile" class="w-8 h-8 rounded-full">
               <i class="fas fa-chevron-down text-xs"></i>
            </button>
         </div>
      </div>
   </div>
</header>

<!-- Mobile Sidebar Overlay -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden hidden"></div>

<script>
// Mobile sidebar toggle
document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
   const sidebar = document.querySelector('.fixed.inset-y-0.left-0');
   const overlay = document.getElementById('sidebar-overlay');
   
   sidebar.classList.toggle('-translate-x-full');
   overlay.classList.toggle('hidden');
});

document.getElementById('sidebar-overlay')?.addEventListener('click', function() {
   const sidebar = document.querySelector('.fixed.inset-y-0.left-0');
   const overlay = document.getElementById('sidebar-overlay');
   
   sidebar.classList.add('-translate-x-full');
   overlay.classList.add('hidden');
});
</script>