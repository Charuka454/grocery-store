<?php

@include 'config.php';
session_start();

$user_id = $_SESSION['user_id'];
if(!isset($user_id)){
   header('location:login.php');
   exit;
}

if(isset($_POST['add_to_wishlist'])){
   $pid = filter_var($_POST['pid'], FILTER_SANITIZE_STRING);
   $p_name = filter_var($_POST['p_name'], FILTER_SANITIZE_STRING);
   $p_price = filter_var($_POST['p_price'], FILTER_SANITIZE_STRING);
   $p_image = filter_var($_POST['p_image'], FILTER_SANITIZE_STRING);

   $check_wishlist_numbers = $conn->prepare("SELECT * FROM wishlist WHERE name = ? AND user_id = ?");
   $check_wishlist_numbers->execute([$p_name, $user_id]);

   $check_cart_numbers = $conn->prepare("SELECT * FROM cart WHERE name = ? AND user_id = ?");
   $check_cart_numbers->execute([$p_name, $user_id]);

   if($check_wishlist_numbers->rowCount() > 0){
      $message[] = 'already added to wishlist!';
   }elseif($check_cart_numbers->rowCount() > 0){
      $message[] = 'already added to cart!';
   }else{
      $insert_wishlist = $conn->prepare("INSERT INTO wishlist(user_id, pid, name, price, image) VALUES(?,?,?,?,?)");
      $insert_wishlist->execute([$user_id, $pid, $p_name, $p_price, $p_image]);
      $message[] = 'added to wishlist!';
   }
}

if(isset($_POST['add_to_cart'])){
   $pid = filter_var($_POST['pid'], FILTER_SANITIZE_STRING);
   $p_name = filter_var($_POST['p_name'], FILTER_SANITIZE_STRING);
   $p_price = filter_var($_POST['p_price'], FILTER_SANITIZE_STRING);
   $p_image = filter_var($_POST['p_image'], FILTER_SANITIZE_STRING);
   $p_qty = filter_var($_POST['p_qty'], FILTER_SANITIZE_STRING);

   $check_cart_numbers = $conn->prepare("SELECT * FROM cart WHERE name = ? AND user_id = ?");
   $check_cart_numbers->execute([$p_name, $user_id]);

   if($check_cart_numbers->rowCount() > 0){
      $message[] = 'already added to cart!';
   }else{
      $check_wishlist_numbers = $conn->prepare("SELECT * FROM wishlist WHERE name = ? AND user_id = ?");
      $check_wishlist_numbers->execute([$p_name, $user_id]);

      if($check_wishlist_numbers->rowCount() > 0){
         $delete_wishlist = $conn->prepare("DELETE FROM wishlist WHERE name = ? AND user_id = ?");
         $delete_wishlist->execute([$p_name, $user_id]);
      }

      $insert_cart = $conn->prepare("INSERT INTO cart(user_id, pid, name, price, quantity, image) VALUES(?,?,?,?,?,?)");
      $insert_cart->execute([$user_id, $pid, $p_name, $p_price, $p_qty, $p_image]);
      $message[] = 'added to cart!';
   }
}

/* -------------------------------------------
   Customer Review backend (drop-in)
--------------------------------------------*/
if (
   $_SERVER['REQUEST_METHOD'] === 'POST' &&
   isset($_POST['review_title'], $_POST['review_email'], $_POST['review_message'])
) {
   $rev_name   = trim(filter_var($_POST['review_name']  ?? '', FILTER_SANITIZE_STRING));
   $rev_email  = trim(filter_var($_POST['review_email'] ?? '', FILTER_SANITIZE_EMAIL));
   $rev_order  = trim(filter_var($_POST['review_order'] ?? '', FILTER_SANITIZE_STRING));
   $rev_title  = trim(filter_var($_POST['review_title'] ?? '', FILTER_SANITIZE_STRING));
   $rev_msg    = trim(filter_var($_POST['review_message'] ?? '', FILTER_SANITIZE_STRING));
   $rev_rating = (int)$_POST['review_rating'] ?? 0;

   $errors = [];
   if ($rev_name === '')   { $errors[] = 'Name is required.'; }
   if (!filter_var($rev_email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Valid email is required.'; }
   if ($rev_title === '')  { $errors[] = 'Title is required.'; }
   if ($rev_msg === '')    { $errors[] = 'Review message is required.'; }
   if ($rev_rating < 1 || $rev_rating > 5) { $errors[] = 'Rating must be between 1 and 5.'; }

   $image_path = null;
   if (isset($_FILES['review_image']) && $_FILES['review_image']['error'] !== UPLOAD_ERR_NO_FILE) {
      if ($_FILES['review_image']['error'] === UPLOAD_ERR_OK) {
         $tmp  = $_FILES['review_image']['tmp_name'];
         $size = (int)$_FILES['review_image']['size'];
         if ($size > 3 * 1024 * 1024) {
            $errors[] = 'Image must be smaller than 3MB.';
         } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $tmp);
            finfo_close($finfo);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($allowed[$mime])) {
               $errors[] = 'Only JPG, PNG, or WEBP images are allowed.';
            } else {
               $ext = $allowed[$mime];
               $safeBase = bin2hex(random_bytes(8));
               $newName = $safeBase . '_' . time() . '.' . $ext;
               $destDir = __DIR__ . '/uploaded_reviews';
               if (!is_dir($destDir)) { @mkdir($destDir, 0755, true); }
               $dest = $destDir . '/' . $newName;
               if (move_uploaded_file($tmp, $dest)) {
                  $image_path = 'uploaded_reviews/' . $newName;
               } else {
                  $errors[] = 'Failed to save uploaded image.';
               }
            }
         }
      } else {
         $errors[] = 'Upload error. Please try again.';
      }
   }

   if (empty($errors)) {
      try {
         $sql = "INSERT INTO reviews (user_id, name, email, order_id, title, rating, message, image_path, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved')";
         $stmt = $conn->prepare($sql);
         $stmt->execute([
            $user_id ?? null,
            $rev_name,
            $rev_email,
            $rev_order !== '' ? $rev_order : null,
            $rev_title,
            $rev_rating,
            $rev_msg,
            $image_path
         ]);
         $message[] = 'Thank you! Your review has been submitted.';
      } catch (Exception $e) {
         $message[] = 'Could not save review. Please try again.';
      }
   } else {
      $message[] = implode(' ', $errors);
   }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Kandu Pinnawala - Premium Sri Lankan Handicrafts</title>

   <!-- Tailwind CDN -->
   <script src="https://cdn.tailwindcss.com"></script>
   <script>
      tailwind.config = {
         theme: {
            extend: {
               colors: {
                  primary: '#8B4513',
                  secondary: '#A0522D',
                  accent: '#D2B48C',
                  dark: '#3E2723',
                  darker: '#1B0F0A'
               },
               fontFamily: {
                  'gaming': ['Orbitron', 'monospace']
               }
            }
         }
      }
   </script>

   <!-- Font Awesome -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

   <!-- Google Fonts -->
   <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

   <style>
      * { margin: 0; padding: 0; box-sizing: border-box; }
      body {
         font-family: 'Inter', sans-serif;
         background: linear-gradient(135deg, #1B0F0A 0%, #3E2723 50%, #5D4037 100%);
         color: white;
         overflow-x: hidden;
      }
      .neon-glow { box-shadow: 0 0 20px rgba(139,69,19,.5), 0 0 40px rgba(160,82,45,.3), 0 0 60px rgba(210,180,140,.2); }
      .glass-effect { background: rgba(255,255,255,.08); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,.18); }
      .hover-glow:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(139,69,19,.35); transition: all .3s ease; }
      .floating-animation { animation: floating 3s ease-in-out infinite; }
      @keyframes floating { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
      .gradient-text { background: linear-gradient(45deg, #8B4513, #A0522D, #D2B48C); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
      .cyber-border { position:relative; border:2px solid transparent; background: linear-gradient(135deg, rgba(139,69,19,.2), rgba(160,82,45,.2)) border-box; -webkit-mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0); -webkit-mask-composite: exclude; }
      .message { position:fixed; top:20px; right:20px; background:rgba(139,69,19,.9); backdrop-filter:blur(10px); color:#fff; padding:15px 20px; border-radius:10px; border:1px solid rgba(255,255,255,.2); z-index:1000; }
      .ai-chat-widget { position:fixed; bottom:30px; right:30px; width:60px; height:60px; background:linear-gradient(135deg,#8B4513,#D2B48C); border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; box-shadow:0 10px 25px rgba(139,69,19,.4); animation:pulse 2s infinite; z-index:1000; }
      @keyframes pulse { 0%{transform:scale(1)} 50%{transform:scale(1.1)} 100%{transform:scale(1)} }
      .hero-bg { background: radial-gradient(circle at 20% 80%, rgba(139,69,19,.35) 0%, transparent 55%), radial-gradient(circle at 80% 20%, rgba(210,180,140,.35) 0%, transparent 55%), radial-gradient(circle at 40% 40%, rgba(160,82,45,.35) 0%, transparent 55%); }
      .category-icon { width:80px; height:80px; background:linear-gradient(135deg, rgba(139,69,19,.25), rgba(160,82,45,.25)); border-radius:20px; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; transition:all .3s ease; }
      .category-icon:hover { transform: rotateY(180deg); background: linear-gradient(135deg, #8B4513, #D2B48C); }

      .text-base{font-size:1.125rem!important;}
      .text-lg{font-size:1.25rem!important;}
      .text-xl{font-size:1.375rem!important;}
      p, label, input, button, a, li { font-size:1.12rem; }

      .product-card{ background: linear-gradient(180deg, rgba(62,39,35,.92), rgba(62,39,35,.8)); border:1px solid rgba(210,180,140,.28); border-radius:22px; backdrop-filter: blur(16px); transition: transform .4s ease, box-shadow .4s ease, border-color .4s ease; }
      .product-card:hover{ transform: translateY(-10px) scale(1.02); border-color: rgba(210,180,140,.6); box-shadow: 0 22px 48px rgba(160,82,45,.35); }
      .product-card .aspect-square{ position:relative; border-radius:18px; border:1px solid rgba(210,180,140,.25); overflow:hidden; background: radial-gradient(600px 120px at 20% 0%, rgba(210,180,140,.18), transparent 60%); }
      .product-card img{ transition: transform .6s ease; }
      .group:hover .product-card img{ transform: scale(1.07); }
      .price-badge{ font-size:1.05rem; letter-spacing:.3px; padding:.6rem 1rem; border:1px solid rgba(255,255,255,.18); box-shadow: 0 6px 18px rgba(210,180,140,.25); }
      .product-title{ font-weight:800; letter-spacing:.2px; color:#FFF7EE; text-shadow: 0 1px 0 rgba(0,0,0,.35); line-height:1.25; }
      .product-card label{ color:#F0E6DA; font-weight:600; }
      .product-card .qty{ background: rgba(255,255,255,.08); }
      .product-card .qty:focus{ outline:none; box-shadow:0 0 0 3px rgba(210,180,140,.35); }
      .product-card button[name="add_to_cart"]{ font-size:1.1rem; letter-spacing:.2px; }
      .product-card .glass-effect{ border-color: rgba(255,255,255,.25); color:#E2C9A8; }

      /* Promotions UI bits */
      .promo-badge{ position:absolute; top:6px; right:6px; z-index:10; padding:.5rem .75rem; border-radius:9999px; font-weight:800; background:linear-gradient(135deg,#22c55e,#86efac); color:#0f172a; border:1px solid rgba(255,255,255,.25); box-shadow:0 10px 25px rgba(16,185,129,.25); letter-spacing:.2px; font-size:.85rem; }
      .old-price{ color:#cbd5e1; opacity:.9; text-decoration: line-through; font-weight:600; }
      .deal-row{ display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; }
   </style>
</head>
<body>

<?php include 'header.php'; ?>

<!-- Hero -->
<section class="relative min-h-screen flex items-center justify-center overflow-hidden hero-bg">
   <div class="absolute top-10 left-10 w-96 h-96 bg-gradient-to-r from-[rgba(139,69,19,0.2)] to-[rgba(210,180,140,0.2)] rounded-full blur-3xl floating-animation"></div>
   <div class="absolute bottom-10 right-10 w-80 h-80 bg-gradient-to-r from-[rgba(160,82,45,0.2)] to-[rgba(139,69,19,0.2)] rounded-full blur-3xl floating-animation" style="animation-delay: 1s;"></div>

   <div class="container mx-auto px-6 lg:px-12 grid lg:grid-cols-2 gap-16 items-center relative z-10">
      <div class="space-y-8">
         <div class="space-y-6">
            <h1 class="text-6xl lg:text-8xl font-bold leading-tight">
               <span class="gradient-text font-gaming">KANDU</span><br>
               <span class="text-white">PINNAWALA</span>
            </h1>
            <div class="h-1 w-32 bg-gradient-to-r from-[#8B4513] to-[#D2B48C] rounded-full"></div>
            <p class="text-xl text-gray-300 leading-relaxed max-w-2xl">
               Discover the ultimate collection of traditional Sri Lankan handicrafts. Where heritage meets innovation in a warm, earthy aesthetic.
            </p>
         </div>

         <div class="flex flex-col sm:flex-row gap-6">
            <a href="#promotions" class="group bg-gradient-to-r from-[#8B4513] to-[#D2B48C] text-white px-8 py-4 rounded-full font-semibold text-lg hover-glow neon-glow">
               <i class="fas fa-rocket mr-2"></i> EXPLORE NOW
            </a>
            <a href="#products" class="glass-effect text-white px-8 py-4 rounded-full font-semibold text-lg hover-glow border border-[rgba(139,69,19,0.5)]">
               <i class="fas fa-play mr-2"></i> WATCH DEMO
            </a>
         </div>

         <div class="flex space-x-8 pt-8">
            <div class="text-center"><div class="text-3xl font-bold gradient-text">1000+</div><div class="text-gray-400 text-sm">Products</div></div>
            <div class="text-center"><div class="text-3xl font-bold gradient-text">500+</div><div class="text-gray-400 text-sm">Happy Customers</div></div>
            <div class="text-center"><div class="text-3xl font-bold gradient-text">50+</div><div class="text-gray-400 text-sm">Artisans</div></div>
         </div>
      </div>

      <div class="relative">
         <div class="glass-effect p-8 rounded-3xl neon-glow">
            <div class="aspect-square rounded-2xl overflow-hidden">
               <img src="images/new.jpg" alt="Sri Lankan Handicrafts" class="w-full h-full object-cover">
            </div>
            <div class="absolute -top-4 -right-4 w-20 h-20 bg-gradient-to-r from-[#8B4513] to-[#D2B48C] rounded-2xl flex items-center justify-center floating-animation">
               <i class="fas fa-star text-2xl"></i>
            </div>
            <div class="absolute -bottom-4 -left-4 w-16 h-16 bg-gradient-to-r from-[#A0522D] to-[#8B4513] rounded-xl flex items-center justify-center floating-animation" style="animation-delay:.5s;">
               <i class="fas fa-heart text-xl"></i>
            </div>
         </div>
      </div>
   </div>
</section>

<!-- Categories -->
<section class="py-20 relative">
   <div class="container mx-auto px-6 lg:px-12">
      <div class="text-center mb-16">
         <h2 class="text-5xl lg:text-6xl font-bold mb-6"><span class="gradient-text font-gaming">CATEGORIES</span></h2>
         <div class="h-1 w-24 bg-gradient-to-r from-[#8B4513] to-[#D2B48C] rounded-full mx-auto mb-6"></div>
         <p class="text-xl text-gray-300 max-w-3xl mx-auto">Choose your craft category and dive into traditional Sri Lankan artistry</p>
      </div>

      <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
         <div class="glass-effect p-8 rounded-3xl text-center hover-glow cyber-border">
            <div class="category-icon"><i class="fas fa-tree text-3xl text-[#CD853F]"></i></div>
            <h3 class="text-2xl font-bold mb-4 gradient-text">WOOD</h3>
            <p class="text-gray-300 mb-6 leading-relaxed">Handcrafted wooden masterpieces showcasing traditional artistry</p>
            <a href="category.php?category=wood" class="inline-flex items-center text-[#CD853F] font-semibold hover:text-[#deb887] transition-colors"><i class="fas fa-arrow-right mr-2"></i>EXPLORE</a>
         </div>

         <div class="glass-effect p-8 rounded-3xl text-center hover-glow cyber-border">
            <div class="category-icon"><i class="fas fa-tshirt text-3xl text-[#deb887]"></i></div>
            <h3 class="text-2xl font-bold mb-4 gradient-text">CLOTHES</h3>
            <p class="text-gray-300 mb-6 leading-relaxed">Traditional garments woven with cultural heritage</p>
            <a href="category.php?category=clothes" class="inline-flex items-center text-[#CD853F] font-semibold hover:text-[#deb887] transition-colors"><i class="fas fa-arrow-right mr-2"></i>EXPLORE</a>
         </div>

         <div class="glass-effect p-8 rounded-3xl text-center hover-glow cyber-border">
            <div class="category-icon"><i class="fas fa-palette text-3xl text-[#A0522D]"></i></div>
            <h3 class="text-2xl font-bold mb-4 gradient-text">WALL ARTS</h3>
            <p class="text-gray-300 mb-6 leading-relaxed">Beautiful decorations reflecting artistic traditions</p>
            <a href="category.php?category=wallarts" class="inline-flex items-center text-[#CD853F] font-semibold hover:text-[#deb887] transition-colors"><i class="fas fa-arrow-right mr-2"></i>EXPLORE</a>
         </div>

         <div class="glass-effect p-8 rounded-3xl text-center hover-glow cyber-border">
            <div class="category-icon"><i class="fas fa-medal text-3xl text-[#FFD166]"></i></div>
            <h3 class="text-2xl font-bold mb-4 gradient-text">BRASS</h3>
            <p class="text-gray-300 mb-6 leading-relaxed">Exquisite brass items by skilled traditional artisans</p>
            <a href="category.php?category=brass" class="inline-flex items-center text-[#CD853F] font-semibold hover:text-[#deb887] transition-colors"><i class="fas fa-arrow-right mr-2"></i>EXPLORE</a>
         </div>
      </div>
   </div>
</section>

<!-- ========================= -->
<!-- Promotions (DB-driven)    -->
<!-- ========================= -->
<section id="promotions" class="py-20 relative">
  <div class="container mx-auto px-6 lg:px-12">
    <div class="text-center mb-16">
      <h2 class="text-5xl lg:text-6xl font-bold mb-6">
        <span class="gradient-text font-gaming">PROMOTIONS</span>
      </h2>
      <div class="h-1 w-24 bg-gradient-to-r from-[#8B4513] to-[#D2B48C] rounded-full mx-auto mb-6"></div>
      <p class="text-xl text-gray-300 max-w-3xl mx-auto">
        Today’s hand-picked deals — managed from your Admin panel.
      </p>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8 lg:gap-10">
      <?php
        $now = date('Y-m-d H:i:s');
        $sql = "
          SELECT p.*, pr.id AS promo_id, pr.promo_price, pr.discount_percent, pr.label
          FROM promotions pr
          JOIN products p ON p.id = pr.product_id
          WHERE pr.active = 1
            AND (pr.starts_at IS NULL OR pr.starts_at <= ?)
            AND (pr.ends_at   IS NULL OR pr.ends_at   >= ?)
          ORDER BY pr.created_at DESC
          LIMIT 6
        ";
        $select_promos = $conn->prepare($sql);
        $select_promos->execute([$now, $now]);

        if($select_promos->rowCount() > 0){
          while($promo = $select_promos->fetch(PDO::FETCH_ASSOC)){
            $basePrice  = (float)$promo['price'];
            $promoPrice = null;

            if (!empty($promo['promo_price'])) {
              $promoPrice = (float)$promo['promo_price'];
            } elseif (!empty($promo['discount_percent'])) {
              $promoPrice = max(0, $basePrice * (1 - ((float)$promo['discount_percent'] / 100)));
            }

            if ($promoPrice !== null && $promoPrice < $basePrice) {
              $was = $basePrice;
              $nowPrice = $promoPrice;
              $save = $was > 0 ? round((($was - $nowPrice) / $was) * 100) : 0;
              $badgeText = !empty($promo['label']) ? htmlspecialchars($promo['label']) : 'Limited Offer';
      ?>
      <form action="" method="POST" class="group">
        <div class="product-card p-6 relative h-full flex flex-col">
          <div class="promo-badge"><?= $badgeText; ?> · SAVE <?= $save; ?>%</div>

          <div class="absolute top-6 left-6 bg-gradient-to-r from-[#8B4513] to-[#D2B48C] text-white px-4 py-2 rounded-full font-bold text-sm z-10 neon-glow price-badge">
            Rs <?= number_format($nowPrice, 2); ?>
          </div>

          <div class="absolute top-6 right-20 flex flex-col gap-2 z-10">
            <button type="submit" name="add_to_wishlist" class="w-11 h-11 glass-effect rounded-full flex items-center justify-center hover:text-white hover:bg-gradient-to-r hover:from-[#8B4513] hover:to-[#D2B48C] transition-all">
              <i class="fas fa-heart"></i>
            </button>
            <a href="view_page.php?pid=<?= $promo['id']; ?>" class="w-11 h-11 glass-effect rounded-full flex items-center justify-center hover:text-white hover:bg-gradient-to-r hover:from-[#8B4513] hover:to-[#D2B48C] transition-all">
              <i class="fas fa-eye"></i>
            </a>
          </div>

          <div class="aspect-square rounded-2xl overflow-hidden mb-6">
            <img src="uploaded_img/<?= $promo['image']; ?>" alt="<?= htmlspecialchars($promo['name']); ?>" class="w-full h-full object-cover">
          </div>

          <div class="space-y-4 mt-auto">
            <h3 class="text-xl product-title"><?= htmlspecialchars($promo['name']); ?></h3>

            <div class="deal-row">
              <span class="old-price">Was Rs <?= number_format($was, 2); ?></span>
              <span class="text-sm px-2 py-1 rounded-md glass-effect border border-white/20">Now Rs <?= number_format($nowPrice, 2); ?></span>
            </div>

            <input type="hidden" name="pid" value="<?= $promo['id']; ?>">
            <input type="hidden" name="p_name" value="<?= htmlspecialchars($promo['name']); ?>">
            <input type="hidden" name="p_price" value="<?= $nowPrice; ?>">
            <input type="hidden" name="p_image" value="<?= htmlspecialchars($promo['image']); ?>">

            <div class="flex items-center gap-3">
              <label class="text-sm font-medium">QTY:</label>
              <input type="number" min="1" value="1" name="p_qty" class="qty w-24 px-3 py-2 glass-effect rounded-lg text-white text-center focus:ring-2 focus:ring-[rgb(139,69,19)] transition-all">
            </div>

            <button type="submit" name="add_to_cart" class="w-full bg-gradient-to-r from-[#8B4513] to-[#D2B48C] text-white py-3.5 rounded-xl font-semibold hover-glow neon-glow transition-all duration-300 transform hover:scale-[1.02]">
              <i class="fas fa-shopping-cart mr-2"></i> ADD TO CART
            </button>
          </div>
        </div>
      </form>
      <?php
            } // end valid promo
          } // end while
        } else {
          echo '<div class="col-span-full text-center py-16">
                  <div class="glass-effect p-12 rounded-3xl max-w-md mx-auto">
                    <i class="fas fa-tags text-6xl" style="color:#22c55e"></i>
                    <p class="text-2xl text-gray-300 font-medium">No promotions right now. Check back soon!</p>
                  </div>
                </div>';
        }
      ?>
    </div>

    <div class="text-center mt-12">
      <a href="shop.php" class="inline-flex items-center bg-gradient-to-r from-[#5D4037] to-[#4E342E] glass-effect text-white px-8 py-4 rounded-full font-semibold text-lg hover-glow transition-all duration-300 transform hover:scale-105">
        <i class="fas fa-store mr-3"></i> VIEW MORE DEALS <i class="fas fa-arrow-right ml-3"></i>
      </a>
    </div>
  </div>
</section>

<!-- Featured -->
<section id="products" class="py-20 relative">
   <div class="container mx-auto px-6 lg:px-12">
      <div class="text-center mb-16">
         <h2 class="text-5xl lg:text-6xl font-bold mb-6"><span class="gradient-text font-gaming">FEATURED</span></h2>
         <div class="h-1 w-24 bg-gradient-to-r from-[#8B4513] to-[#D2B48C] rounded-full mx-auto mb-6"></div>
         <p class="text-xl text-gray-300 max-w-3xl mx-auto">Discover our premium collection of authentic Sri Lankan handicrafts</p>
      </div>

      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8 lg:gap-10">
         <?php
            $select_products = $conn->prepare("SELECT * FROM products LIMIT 6");
            $select_products->execute();
            if($select_products->rowCount() > 0){
               while($fetch_products = $select_products->fetch(PDO::FETCH_ASSOC)){
         ?>
         <form action="" method="POST" class="group">
            <div class="product-card p-6 relative h-full flex flex-col">
               <div class="absolute top-6 left-6 bg-gradient-to-r from-[#8B4513] to-[#D2B48C] text-white px-4 py-2 rounded-full font-bold text-sm z-10 neon-glow price-badge">
                  Rs <?= $fetch_products['price']; ?>
               </div>

               <div class="absolute top-6 right-6 flex flex-col gap-2 z-10">
                  <button type="submit" name="add_to_wishlist" class="w-11 h-11 glass-effect rounded-full flex items-center justify-center hover:text-white hover:bg-gradient-to-r hover:from-[#8B4513] hover:to-[#D2B48C] transition-all">
                     <i class="fas fa-heart"></i>
                  </button>
                  <a href="view_page.php?pid=<?= $fetch_products['id']; ?>" class="w-11 h-11 glass-effect rounded-full flex items-center justify-center hover:text-white hover:bg-gradient-to-r hover:from-[#8B4513] hover:to-[#D2B48C] transition-all">
                     <i class="fas fa-eye"></i>
                  </a>
               </div>

               <div class="aspect-square rounded-2xl overflow-hidden mb-6">
                  <img src="uploaded_img/<?= $fetch_products['image']; ?>" alt="<?= $fetch_products['name']; ?>" class="w-full h-full object-cover">
               </div>

               <div class="space-y-4 mt-auto">
                  <h3 class="text-xl product-title"><?= $fetch_products['name']; ?></h3>

                  <input type="hidden" name="pid" value="<?= $fetch_products['id']; ?>">
                  <input type="hidden" name="p_name" value="<?= $fetch_products['name']; ?>">
                  <input type="hidden" name="p_price" value="<?= $fetch_products['price']; ?>">
                  <input type="hidden" name="p_image" value="<?= $fetch_products['image']; ?>">

                  <div class="flex items-center gap-3">
                     <label class="text-sm font-medium">QTY:</label>
                     <input type="number" min="1" value="1" name="p_qty" class="qty w-24 px-3 py-2 glass-effect rounded-lg text-white text-center focus:ring-2 focus:ring-[rgb(139,69,19)] transition-all">
                  </div>

                  <button type="submit" name="add_to_cart" class="w-full bg-gradient-to-r from-[#8B4513] to-[#D2B48C] text-white py-3.5 rounded-xl font-semibold hover-glow neon-glow transition-all duration-300 transform hover:scale-[1.02]">
                     <i class="fas fa-shopping-cart mr-2"></i> ADD TO CART
                  </button>
               </div>
            </div>
         </form>
         <?php
               }
            }else{
               echo '<div class="col-span-full text-center py-16">
                     <div class="glass-effect p-12 rounded-3xl max-w-md mx-auto">
                        <i class="fas fa-box-open text-6xl" style="color:#CD853F"></i>
                        <p class="text-2xl text-gray-300 font-medium">No products available yet!</p>
                     </div>
                  </div>';
            }
         ?>
      </div>

      <div class="text-center mt-12">
         <a href="shop.php" class="inline-flex items-center bg-gradient-to-r from-[#5D4037] to-[#4E342E] glass-effect text-white px-8 py-4 rounded-full font-semibold text-lg hover-glow transition-all duration-300 transform hover:scale-105">
            <i class="fas fa-store mr-3"></i> VIEW ALL PRODUCTS <i class="fas fa-arrow-right ml-3"></i>
         </a>
      </div>
   </div>
</section>

<!-- Stats -->
<section class="py-20 relative">
   <div class="container mx-auto px-6 lg:px-12">
      <div class="glass-effect rounded-3xl p-12">
         <div class="grid md:grid-cols-4 gap-8 text-center">
            <div><div class="text-5xl font-bold gradient-text mb-2">1000+</div><div class="text-gray-300">Premium Products</div></div>
            <div><div class="text-5xl font-bold gradient-text mb-2">500+</div><div class="text-gray-300">Happy Customers</div></div>
            <div><div class="text-5xl font-bold gradient-text mb-2">50+</div><div class="text-gray-300">Master Artisans</div></div>
            <div><div class="text-5xl font-bold gradient-text mb-2">24/7</div><div class="text-gray-300">Customer Support</div></div>
         </div>
      </div>
   </div>
</section>

<!-- AI Chat Widget -->
<div class="ai-chat-widget" onclick="toggleChat()"><i class="fas fa-robot text-2xl"></i></div>

<!-- Chat Interface -->
<div id="chatInterface" class="fixed bottom-20 right-8 w-80 h-96 glass-effect rounded-3xl p-6 transform translate-y-full opacity-0 transition-all duration-300 z-50" style="display:none;">
   <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-bold gradient-text">AI Assistant</h3>
      <button onclick="toggleChat()" class="text-gray-400 hover:text-white"><i class="fas fa-times"></i></button>
   </div>
   <div class="h-64 overflow-y-auto mb-4 space-y-3" id="chatMessages">
      <div class="bg-[rgba(139,69,19,0.2)] p-3 rounded-lg"><p class="text-sm text-gray-300">Hello! I'm here to help you find the perfect Sri Lankan handicraft. What are you looking for?</p></div>
   </div>
   <div class="flex gap-2">
      <input type="text" id="chatInput" placeholder="Type your message..." class="flex-1 px-4 py-2 glass-effect rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[rgb(139,69,19)]">
      <button onclick="sendMessage()" class="bg-gradient-to-r from-[#8B4513] to-[#D2B48C] px-4 py-2 rounded-lg hover-glow"><i class="fas fa-paper-plane"></i></button>
   </div>
</div>

<?php include 'about.php'; ?>

<!-- Reviews -->
<section id="customer-reviews" class="py-20 relative">
  <div class="container mx-auto px-6 lg:px-12">
    <div class="text-center mb-12">
      <h2 class="text-4xl lg:text-5xl font-bold mb-4 text-white">What Customers Say</h2>
      <p class="text-lg text-gray-300 max-w-2xl mx-auto">Latest verified feedback from our community.</p>
      <div class="h-1 w-24 bg-gradient-to-r from-[#8B4513] to-[#D2B48C] rounded-full mx-auto mt-6"></div>
    </div>

    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
      <?php
        try {
          $revQ = $conn->prepare("SELECT name, title, rating, message, image_path, created_at FROM reviews WHERE status='approved' ORDER BY created_at DESC LIMIT 6");
          $revQ->execute();
          if ($revQ->rowCount() > 0):
            while ($r = $revQ->fetch(PDO::FETCH_ASSOC)):
              $rName  = htmlspecialchars($r['name']);
              $rTitle = htmlspecialchars($r['title']);
              $rMsg   = htmlspecialchars($r['message']);
              $rRate  = (int)$r['rating'];
              $rImg   = $r['image_path'];
      ?>
        <div class="rounded-3xl overflow-hidden shadow-lg border border-white/20 bg-white/10 backdrop-blur-xl p-6">
          <div class="flex items-center gap-4 mb-3">
            <div class="w-12 h-12 rounded-full bg-gradient-to-r from-[#8B4513] to-[#D2B48C] flex items-center justify-center text-white font-bold">
              <?php echo strtoupper(substr($rName,0,1)); ?>
            </div>
            <div>
              <h3 class="text-lg font-bold text-white leading-tight"><?php echo $rName; ?></h3>
              <div class="text-base" style="color:#FFD166">
                <?php for($i=1;$i<=5;$i++){ echo $i <= $rRate ? '<i class="fas fa-star mr-0.5"></i>' : '<i class="far fa-star mr-0.5"></i>'; } ?>
              </div>
            </div>
          </div>

          <h4 class="text-white font-semibold mb-2"><?php echo $rTitle; ?></h4>
          <p class="text-gray-200 leading-relaxed mb-3"><?php echo $rMsg; ?></p>

          <?php if (!empty($rImg)): ?>
            <div class="rounded-xl overflow-hidden border border-white/10">
              <img src="<?php echo htmlspecialchars($rImg); ?>" alt="review image" class="w-full h-40 object-cover">
            </div>
          <?php endif; ?>
        </div>
      <?php
            endwhile;
          else:
            echo '<div class="col-span-full text-center text-gray-300">No customer reviews yet. Be the first to leave one!</div>';
          endif;
        } catch (Exception $e) {
          echo '<div class="col-span-full text-center text-red-200">Failed to load reviews.</div>';
        }
      ?>
    </div>
  </div>
</section>

<!-- Review Form -->
<section id="leave-review" class="py-20 relative">
  <div class="container mx-auto px-6 lg:px-12">
    <div class="text-center mb-12">
      <h2 class="text-4xl lg:text-5xl font-bold mb-4 text-white">Leave a Review</h2>
      <p class="text-lg text-gray-300 max-w-2xl mx-auto">Share your experience with Kandu Pinnawala. Your feedback helps us and other customers!</p>
      <div class="h-1 w-24 bg-gradient-to-r from-[#8B4513] to-[#D2B48C] rounded-full mx-auto mt-6"></div>
    </div>

    <form action="" method="POST" enctype="multipart/form-data" class="max-w-4xl mx-auto">
      <div class="glass-effect rounded-3xl p-8 md:p-10 shadow-xl border border-white/20">
        <div class="grid md:grid-cols-2 gap-6">
          <div><label class="block text-sm font-semibold text-gray-200 mb-2">Full Name</label><input type="text" name="review_name" required class="w-full px-4 py-3 rounded-xl bg-white/10 text-white placeholder-gray-400 border border-white/20 focus:outline-none focus:ring-2 focus:ring-[rgba(139,69,19,0.7)]" placeholder="e.g., Nimal Perera"></div>
          <div><label class="block text-sm font-semibold text-gray-200 mb-2">Email</label><input type="email" name="review_email" required class="w-full px-4 py-3 rounded-xl bg-white/10 text-white placeholder-gray-400 border border-white/20 focus:outline-none focus:ring-2 focus:ring-[rgba(139,69,19,0.7)]" placeholder="you@example.com"></div>
          <div><label class="block text-sm font-semibold text-gray-200 mb-2">Order ID (optional)</label><input type="text" name="review_order" class="w-full px-4 py-3 rounded-xl bg-white/10 text-white placeholder-gray-400 border border-white/20 focus:outline-none focus:ring-2 focus:ring-[rgba(139,69,19,0.7)]" placeholder="e.g., KP-2025-00123"></div>
          <div><label class="block text-sm font-semibold text-gray-200 mb-2">Review Title</label><input type="text" name="review_title" required class="w-full px-4 py-3 rounded-xl bg-white/10 text-white placeholder-gray-400 border border-white/20 focus:outline-none focus:ring-2 focus:ring-[rgba(139,69,19,0.7)]" placeholder="e.g., Beautiful craftsmanship!"></div>
        </div>

        <div class="mt-6">
          <label class="block text-sm font-semibold text-gray-200 mb-2">Rating</label>
          <div class="flex items-center gap-2" id="ratingStars" data-selected="0">
            <button type="button" aria-label="1 star" data-value="1" class="star-btn w-10 h-10 rounded-full bg-white/10 border border-white/20 flex items-center justify-center hover:bg-white/20"><i class="fa-solid fa-star"></i></button>
            <button type="button" aria-label="2 stars" data-value="2" class="star-btn w-10 h-10 rounded-full bg-white/10 border border-white/20 flex items-center justify-center hover:bg-white/20"><i class="fa-solid fa-star"></i></button>
            <button type="button" aria-label="3 stars" data-value="3" class="star-btn w-10 h-10 rounded-full bg-white/10 border border-white/20 flex items-center justify-center hover:bg-white/20"><i class="fa-solid fa-star"></i></button>
            <button type="button" aria-label="4 stars" data-value="4" class="star-btn w-10 h-10 rounded-full bg-white/10 border border-white/20 flex items-center justify-center hover:bg-white/20"><i class="fa-solid fa-star"></i></button>
            <button type="button" aria-label="5 stars" data-value="5" class="star-btn w-10 h-10 rounded-full bg-white/10 border border-white/20 flex items-center justify-center hover:bg-white/20"><i class="fa-solid fa-star"></i></button>
          </div>
          <input type="hidden" name="review_rating" id="review_rating" value="0">
        </div>

        <div class="mt-6">
          <label class="block text-sm font-semibold text-gray-200 mb-2">Your Review</label>
          <textarea name="review_message" rows="5" required class="w-full px-4 py-3 rounded-xl bg-white/10 text-white placeholder-gray-400 border border-white/20 focus:outline-none focus:ring-2 focus:ring-[rgba(139,69,19,0.7)]" placeholder="Tell us about the product quality, delivery, and your experience."></textarea>
          <div class="flex items-center justify-between mt-2 text-xs text-gray-300"><span>Be respectful. Keep it helpful for other shoppers.</span><span id="charCount">0/1000</span></div>
        </div>

        <div class="mt-6 grid md:grid-cols-2 gap-6">
          <div><label class="block text-sm font-semibold text-gray-200 mb-2">Add a photo (optional)</label><input type="file" name="review_image" accept="image/*" class="w-full file:mr-4 file:rounded-lg file:border-0 file:px-4 file:py-2 file:bg-gradient-to-r file:from-[#8B4513] file:to-[#D2B48C] file:text-white file:cursor-pointer rounded-xl bg-white/10 text-white border border-white/20"></div>
          <div class="flex items-center gap-2 mt-8 md:mt-0"><input id="agree" type="checkbox" required class="w-4 h-4 rounded border-white/30 bg-white/10"><label for="agree" class="text-sm text-gray-300">I agree to have my review displayed on the website.</label></div>
        </div>

        <div class="mt-8 flex items-center gap-4">
          <button type="submit" class="inline-flex items-center bg-gradient-to-r from-[#8B4513] to-[#D2B48C] text-white px-8 py-3 rounded-xl font-semibold hover:shadow-xl hover:shadow-[rgba(139,69,19,0.25)] transition"><i class="fa-solid fa-paper-plane mr-2"></i> Submit Review</button>
          <span class="text-xs text-gray-300">*This form is UI only. Backend not included.</span>
        </div>
      </div>
    </form>
  </div>
</section>

<?php include 'footer.php'; ?>

<script>
   function toggleChat() {
      const chatInterface = document.getElementById('chatInterface');
      if (chatInterface.style.display === 'none') {
         chatInterface.style.display = 'block';
         setTimeout(() => { chatInterface.classList.remove('translate-y-full','opacity-0'); }, 10);
      } else {
         chatInterface.classList.add('translate-y-full','opacity-0');
         setTimeout(() => { chatInterface.style.display = 'none'; }, 300);
      }
   }
   function sendMessage() {
      const input = document.getElementById('chatInput');
      const messages = document.getElementById('chatMessages');
      if (input.value.trim()) {
         messages.innerHTML += `
            <div class="text-right">
              <div class="inline-block bg-gradient-to-r from-[#8B4513] to-[#D2B48C] p-3 rounded-lg max-w-xs">
                 <p class="text-sm text-white">${input.value}</p>
              </div>
            </div>`;
         setTimeout(() => {
            messages.innerHTML += `
              <div class="bg-[rgba(139,69,19,0.2)] p-3 rounded-lg">
                <p class="text-sm text-gray-300">Thank you! Please browse our categories or contact us directly.</p>
              </div>`;
            messages.scrollTop = messages.scrollHeight;
         }, 800);
         input.value = '';
         messages.scrollTop = messages.scrollHeight;
      }
   }
   document.querySelectorAll('a[href^="#"]').forEach(a=>{
      a.addEventListener('click',e=>{ e.preventDefault(); document.querySelector(a.getAttribute('href')).scrollIntoView({behavior:'smooth'}); });
   });
   (function(){
      const c = document.getElementById('ratingStars'); const h = document.getElementById('review_rating');
      if(!c||!h) return; const stars=[...c.querySelectorAll('.star-btn')];
      const active='linear-gradient(135deg, #8B4513, #D2B48C)'; const inactive='rgba(255,255,255,0.08)';
      function paint(n){ stars.forEach((b,i)=>{ b.style.background=(i<n)?active:inactive; b.style.borderColor=(i<n)?'rgba(210,180,140,0.6)':'rgba(255,255,255,0.2)'; b.querySelector('i').style.color=(i<n)?'#fff':'#bbb'; }); }
      stars.forEach(b=>{ b.addEventListener('click',()=>{ const v=Number(b.dataset.value||0); h.value=v; c.dataset.selected=v; paint(v); });
                         b.addEventListener('mouseenter',()=>paint(Number(b.dataset.value||0)));
                         b.addEventListener('mouseleave',()=>paint(Number(c.dataset.selected||0)));});
      paint(0);
   })();
   (function(){
      const ta=document.querySelector('textarea[name="review_message"]'); const cc=document.getElementById('charCount'); if(!ta||!cc) return; const max=1000;
      ta.addEventListener('input',()=>{ const len=ta.value.length; cc.textContent=`${len}/${max}`; if(len>max){ ta.value=ta.value.slice(0,max); } });
   })();
</script>

<script src="js/script.js"></script>
</body>
</html>
