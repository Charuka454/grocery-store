<?php
@include 'config.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;
if(!$user_id){
   header('location:login.php');
   exit;
}

$message = [];

/* ================= Helpers ================= */
function getLivePromoPrice(PDO $conn, int $pid, float $basePrice): float {
   // Guard against bogus inputs
   if (!is_finite($basePrice) || $basePrice < 0) $basePrice = 0.0;

   $now = date('Y-m-d H:i:s');
   $q = $conn->prepare("
      SELECT promo_price, discount_percent
      FROM promotions
      WHERE product_id = ? AND active = 1
        AND (starts_at IS NULL OR starts_at <= ?)
        AND (ends_at   IS NULL OR ends_at   >= ?)
      ORDER BY id DESC
      LIMIT 1
   ");
   $q->execute([$pid, $now, $now]);
   if ($row = $q->fetch(PDO::FETCH_ASSOC)) {
      $hasPromoPrice = isset($row['promo_price']) && $row['promo_price'] !== '' && is_numeric($row['promo_price']);
      $hasDisc       = isset($row['discount_percent']) && $row['discount_percent'] !== '' && is_numeric($row['discount_percent']);

      if ($hasPromoPrice) {
         $pp = (float)$row['promo_price'];
         return ($pp >= 0 && $pp < $basePrice) ? $pp : $basePrice;
      }
      if ($hasDisc) {
         $disc = (float)$row['discount_percent'];
         if ($disc > 0 && $disc <= 95) {
            $calc = max(0.0, $basePrice * (1 - $disc/100));
            return ($calc < $basePrice - 0.0001) ? $calc : $basePrice; // tolerate float noise
         }
      }
   }
   return $basePrice;
}

/* ============= Wishlist (trust pid) ============= */
if(isset($_POST['add_to_wishlist'])){
   $pid = (int)($_POST['pid'] ?? 0);
   $pstmt = $conn->prepare("SELECT id, name, price, image FROM products WHERE id = ? LIMIT 1");
   $pstmt->execute([$pid]);
   $prod = $pstmt->fetch(PDO::FETCH_ASSOC);

   if(!$prod){
      $message[] = 'Product not found.';
   }else{
      $checkW = $conn->prepare("SELECT 1 FROM wishlist WHERE pid = ? AND user_id = ? LIMIT 1");
      $checkW->execute([$pid, $user_id]);

      $checkC = $conn->prepare("SELECT 1 FROM cart WHERE pid = ? AND user_id = ? LIMIT 1");
      $checkC->execute([$pid, $user_id]);

      if($checkW->rowCount() > 0){
         $message[] = 'Already in wishlist!';
      }elseif($checkC->rowCount() > 0){
         $message[] = 'Already in cart!';
      }else{
         $ins = $conn->prepare("INSERT INTO wishlist(user_id, pid, name, price, image) VALUES(?,?,?,?,?)");
         $ins->execute([$user_id, $prod['id'], $prod['name'], $prod['price'], $prod['image']]);
         $message[] = 'Added to wishlist!';
      }
   }
}

/* ========= Add to CART with stock subtraction + promo price ========= */
if(isset($_POST['add_to_cart'])){
   $pid  = (int)($_POST['pid'] ?? 0);
   $reqQ = isset($_POST['p_qty']) && is_numeric($_POST['p_qty']) ? (int)$_POST['p_qty'] : 1;
   $reqQ = max(1, $reqQ);

   try{
      $conn->beginTransaction();

      // Lock product row
      $pstmt = $conn->prepare("SELECT id, name, price, image, quantity FROM products WHERE id = ? FOR UPDATE");
      $pstmt->execute([$pid]);
      $prod = $pstmt->fetch(PDO::FETCH_ASSOC);

      if(!$prod){
         $conn->rollBack();
         $message[] = 'Product not found.';
      }else{
         $avail     = (int)($prod['quantity'] ?? 0);
         if ($avail <= 0){
            $conn->rollBack();
            $message[] = 'Out of stock.';
         }else{
            $addQty    = min($reqQ, $avail);
            $newStock  = $avail - $addQty;

            $basePrice = (float)$prod['price'];
            $unitPrice = getLivePromoPrice($conn, $pid, $basePrice);

            // Lock/Upsert cart row
            $csel = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND pid = ? FOR UPDATE");
            $csel->execute([$user_id, $pid]);

            if($row = $csel->fetch(PDO::FETCH_ASSOC)){
               $newCartQty = (int)$row['quantity'] + $addQty;
               $cupd = $conn->prepare("UPDATE cart SET quantity = ?, price = ?, name = ?, image = ? WHERE id = ?");
               $cupd->execute([$newCartQty, $unitPrice, $prod['name'], $prod['image'], $row['id']]);
            }else{
               $cins = $conn->prepare("INSERT INTO cart (user_id, pid, name, price, quantity, image) VALUES (?,?,?,?,?,?)");
               $cins->execute([$user_id, $prod['id'], $prod['name'], $unitPrice, $addQty, $prod['image']]);
            }

            // Subtract stock
            $up = $conn->prepare("UPDATE products SET quantity = ? WHERE id = ?");
            $up->execute([$newStock, $pid]);

            $conn->commit();

            if($addQty < $reqQ){
               $message[] = "Only {$addQty} left; added {$addQty} to cart.";
            }else{
               $message[] = 'Added to cart!';
            }
         }
      }
   }catch(Exception $e){
      if($conn->inTransaction()){ $conn->rollBack(); }
      $message[] = 'Could not add to cart. Please try again.';
   }
}

/* ------------- Reviews backend (small cast/guard fix) ------------- */
if (
   $_SERVER['REQUEST_METHOD'] === 'POST' &&
   isset($_POST['review_title'], $_POST['review_email'], $_POST['review_message']) &&
   !isset($_POST['add_to_cart']) && !isset($_POST['add_to_wishlist'])
) {
   $rev_name   = trim(filter_var($_POST['review_name']  ?? '', FILTER_SANITIZE_STRING));
   $rev_email  = trim(filter_var($_POST['review_email'] ?? '', FILTER_SANITIZE_EMAIL));
   $rev_order  = trim(filter_var($_POST['review_order'] ?? '', FILTER_SANITIZE_STRING));
   $rev_title  = trim(filter_var($_POST['review_title'] ?? '', FILTER_SANITIZE_STRING));
   $rev_msg    = trim(filter_var($_POST['review_message'] ?? '', FILTER_SANITIZE_STRING));
   $rev_rating = isset($_POST['review_rating']) && is_numeric($_POST['review_rating']) ? (int)$_POST['review_rating'] : 0;

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
               fontFamily: { gaming: ['Orbitron','monospace'] }
            }
         }
      }
   </script>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

   <style>
      *{box-sizing:border-box}
      body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#1B0F0A 0%,#3E2723 50%,#5D4037 100%);color:#fff;overflow-x:hidden}
      .neon-glow{box-shadow:0 0 20px rgba(139,69,19,.5),0 0 40px rgba(160,82,45,.3),0 0 60px rgba(210,180,140,.2)}
      .glass-effect{background:rgba(255,255,255,.08);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.18)}
      .hover-glow:hover{transform:translateY(-5px);box-shadow:0 10px 25px rgba(139,69,19,.35);transition:all .3s ease}
      .floating-animation{animation:floating 3s ease-in-out infinite}
      @keyframes floating{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
      .gradient-text{background:linear-gradient(45deg,#8B4513,#A0522D,#D2B48C);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
      .hero-bg{background:radial-gradient(circle at 20% 80%,rgba(139,69,19,.35) 0%,transparent 55%),radial-gradient(circle at 80% 20%,rgba(210,180,140,.35) 0%,transparent 55%),radial-gradient(circle at 40% 40%,rgba(160,82,45,.35) 0%,transparent 55%)}

      /* ===== Product cards + legibility fixes ===== */
      .product-card{background:linear-gradient(180deg,rgba(62,39,35,.92),rgba(62,39,35,.8));border:1px solid rgba(210,180,140,.28);border-radius:22px;backdrop-filter:blur(16px);transition:transform .35s ease,box-shadow .35s ease}
      .product-card:hover{transform:translateY(-8px);box-shadow:0 22px 48px rgba(160,82,45,.35)}
      .product-card .aspect-square{position:relative;border-radius:18px;border:1px solid rgba(210,180,140,.25);overflow:hidden;background:radial-gradient(600px 120px at 20% 0%,rgba(210,180,140,.18),transparent 60%)}

      .product-title{color:#fff !important;font-weight:800;letter-spacing:.2px;line-height:1.25;text-shadow:0 1px 1px rgba(0,0,0,.65)}
      .line-2{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}

      .price-badge{color:#fff !important;background:linear-gradient(135deg,#8B4513,#C08A5A) !important;font-weight:900;text-shadow:0 1px 0 rgba(0,0,0,.55);border:1px solid rgba(255,255,255,.18);padding:.6rem 1rem}
      .old-price{color:#e2e8f0 !important;opacity:.98;text-decoration:line-through;font-weight:600;text-shadow:0 1px 1px rgba(0,0,0,.35)}
      .deal-row{display:flex;align-items:center;gap:.75rem;flex-wrap:wrap}

      .info-panel{background:linear-gradient(180deg,rgba(16,10,7,.92),rgba(16,10,7,.82));border:1px solid rgba(210,180,140,.18);border-radius:16px;padding:14px;box-shadow:inset 0 1px 0 rgba(255,255,255,.05)}

      .promo-badge{position:absolute;top:10px;right:96px;z-index:10;padding:.5rem .75rem;border-radius:9999px;font-weight:800;background:linear-gradient(135deg,#22c55e,#86efac);color:#0f172a;border:1px solid rgba(255,255,255,.25);box-shadow:0 10px 25px rgba(16,185,129,.25);letter-spacing:.2px;font-size:.85rem}
      .badge-stock{position:absolute;top:10px;right:10px;z-index:10}
      .pill{background:#fef3c7;color:#92400e;border:1px solid #f59e0b}
      .badge-oos{background:#fee2e2 !important;color:#991b1b !important;border-color:#ef4444 !important}
      .card-actions{position:absolute;top:10px;right:10px;z-index:10;display:flex;flex-direction:column;gap:.5rem}

      .oos-overlay{position:absolute;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;font-weight:800;letter-spacing:.5px}
   </style>
</head>
<body>

<?php include 'header.php'; ?>

<?php if(!empty($message)): ?>
  <div class="fixed top-5 right-5 space-y-2 z-50">
    <?php foreach($message as $m): ?>
      <div class="rounded-lg shadow neon-glow px-4 py-3" style="background:rgba(139,69,19,.9);border:1px solid rgba(255,255,255,.2)"><?= htmlspecialchars($m) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- ================= HERO ================= -->
<section class="relative min-h-screen flex items-center justify-center overflow-hidden hero-bg">
  <div class="container mx-auto px-6 lg:px-12 grid lg:grid-cols-2 gap-16 items-center relative z-10">
    <div class="space-y-8">
      <div class="space-y-6">
        <h1 class="text-6xl lg:text-8xl font-bold leading-tight">
          <span class="gradient-text font-gaming">KANDU</span><br><span class="text-white">PINNAWALA</span>
        </h1>
        <div class="h-1 w-32 bg-gradient-to-r from-[#8B4513] to-[#D2B48C] rounded-full"></div>
        <p class="text-xl text-gray-300 leading-relaxed max-w-2xl">Discover the ultimate collection of traditional Sri Lankan handicrafts. Where heritage meets innovation in a warm, earthy aesthetic.</p>
      </div>
      <div class="flex flex-col sm:flex-row gap-6">
        <a href="#promotions" class="group bg-gradient-to-r from-[#8B4513] to-[#D2B48C] text-white px-8 py-4 rounded-full font-semibold text-lg hover-glow neon-glow"><i class="fas fa-rocket mr-2"></i> EXPLORE NOW</a>
        <a href="#products" class="glass-effect text-white px-8 py-4 rounded-full font-semibold text-lg hover-glow border border-[rgba(139,69,19,0.5)]"><i class="fas fa-play mr-2"></i> WATCH DEMO</a>
      </div>
    </div>
    <div class="relative">
      <div class="glass-effect p-8 rounded-3xl neon-glow">
        <div class="aspect-square rounded-2xl overflow-hidden"><img src="images/new.jpg" alt="Sri Lankan Handicrafts" class="w-full h-full object-cover"></div>
      </div>
    </div>
  </div>
</section>

<!-- ================= PROMOTIONS ================= -->
<section id="promotions" class="py-20 relative">
  <div class="container mx-auto px-6 lg:px-12">
    <div class="text-center mb-16">
      <h2 class="text-5xl lg:text-6xl font-bold mb-6"><span class="gradient-text font-gaming">PROMOTIONS</span></h2>
      <div class="h-1 w-24 bg-gradient-to-r from-[#8B4513] to-[#D2B48C] rounded-full mx-auto mb-6"></div>
      <p class="text-xl text-gray-300 max-w-3xl mx-auto">Today’s hand-picked deals — managed from your Admin panel.</p>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8 lg:gap-10">
      <?php
        $now = date('Y-m-d H:i:s');
        $sql = "
          SELECT p.*, pr.id AS promo_row_id, pr.promo_price, pr.discount_percent, pr.label
          FROM promotions pr
          JOIN products p ON p.id = pr.product_id
          WHERE pr.active = 1
            AND (pr.starts_at IS NULL OR pr.starts_at <= ?)
            AND (pr.ends_at   IS NULL OR pr.ends_at   >= ?)
          ORDER BY pr.id DESC
          LIMIT 6
        ";
        $select_promos = $conn->prepare($sql);
        $select_promos->execute([$now, $now]);

        if($select_promos->rowCount() > 0){
          while($promo = $select_promos->fetch(PDO::FETCH_ASSOC)){
            $pid       = (int)$promo['id']; // product id
            $basePrice = (float)$promo['price'];
            $qty       = (int)($promo['quantity'] ?? 0);
            $inStock   = $qty > 0;

            $nowPrice  = getLivePromoPrice($conn, $pid, $basePrice);
            if ($nowPrice >= $basePrice - 0.0001) { continue; } // skip if not actually discounted
            $save = $basePrice > 0 ? round((($basePrice - $nowPrice)/$basePrice)*100) : 0;
            $badgeText = !empty($promo['label']) ? htmlspecialchars($promo['label']) : 'Limited Offer';
      ?>
      <form action="" method="POST" class="group">
        <!-- hidden first so any submit button posts pid -->
        <input type="hidden" name="pid" value="<?= $pid; ?>">

        <div class="product-card p-6 relative h-full flex flex-col">
          <div class="promo-badge"><?= $badgeText; ?> · SAVE <?= $save; ?>%</div>

          <div class="absolute top-6 left-6 price-badge z-10">
            Rs <?= number_format($nowPrice, 2); ?>
          </div>

          <div class="badge-stock">
            <?php if(!$inStock): ?>
              <span class="pill badge-oos text-xs px-2 py-1 rounded border">Out of stock</span>
            <?php elseif($qty < 10): ?>
              <span class="pill text-xs px-2 py-1 rounded border">Only <?= $qty; ?> left</span>
            <?php endif; ?>
          </div>

          <div class="card-actions">
            <button type="submit" name="add_to_wishlist" class="w-11 h-11 glass-effect rounded-full flex items-center justify-center hover:text-white hover:bg-gradient-to-r hover:from-[#8B4513] hover:to-[#D2B48C]" aria-label="Wishlist">
              <i class="fas fa-heart"></i>
            </button>
            <a href="view_page.php?pid=<?= $pid; ?>" class="w-11 h-11 glass-effect rounded-full flex items-center justify-center hover:text-white hover:bg-gradient-to-r hover:from-[#8B4513] hover:to-[#D2B48C]" aria-label="View">
              <i class="fas fa-eye"></i>
            </a>
          </div>

          <div class="aspect-square rounded-2xl overflow-hidden mb-6 relative">
            <img src="uploaded_img/<?= htmlspecialchars($promo['image']); ?>" alt="<?= htmlspecialchars($promo['name']); ?>" class="w-full h-full object-cover">
            <?php if(!$inStock): ?><div class="oos-overlay text-white text-lg rounded">OUT OF STOCK</div><?php endif; ?>
          </div>

          <div class="info-panel mt-auto space-y-4">
            <h3 class="text-xl product-title line-2"><?= htmlspecialchars($promo['name']); ?></h3>

            <div class="deal-row">
              <span class="old-price">Was Rs <?= number_format($basePrice, 2); ?></span>
              <span class="text-sm px-2 py-1 rounded-md glass-effect border border-white/20">
                Now Rs <?= number_format($nowPrice, 2); ?>
              </span>
            </div>

            <div class="flex items-center gap-3">
              <label class="text-sm font-medium">QTY:</label>
              <input type="number" min="1" value="<?= $inStock ? 1 : 0; ?>" name="p_qty"
                     class="qty w-24 px-3 py-2 glass-effect rounded-lg text-white text-center focus:ring-2 focus:ring-[rgb(139,69,19)]"
                     <?= $inStock ? '' : 'disabled'; ?>>
            </div>

            <button type="submit" name="add_to_cart"
                    class="w-full bg-gradient-to-r from-[#8B4513] to-[#D2B48C] text-white py-3.5 rounded-xl font-semibold hover-glow neon-glow transition"
                    <?= $inStock ? '' : 'disabled style="opacity:.6;cursor:not-allowed"'; ?>>
              <i class="fas fa-shopping-cart mr-2"></i> <?= $inStock ? 'ADD TO CART' : 'UNAVAILABLE' ?>
            </button>
          </div>
        </div>
      </form>
      <?php
          }
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
      <a href="shop.php" class="inline-flex items-center bg-gradient-to-r from-[#5D4037] to-[#4E342E] glass-effect text-white px-8 py-4 rounded-full font-semibold text-lg hover-glow transition">
        <i class="fas fa-store mr-3"></i> VIEW MORE DEALS <i class="fas fa-arrow-right ml-3"></i>
      </a>
    </div>
  </div>
</section>

<!-- ================= FEATURED ================= -->
<section id="products" class="py-20 relative">
  <div class="container mx-auto px-6 lg:px-12">
    <div class="text-center mb-16">
      <h2 class="text-5xl lg:text-6xl font-bold mb-6"><span class="gradient-text font-gaming">FEATURED</span></h2>
      <div class="h-1 w-24 bg-gradient-to-r from-[#8B4513] to-[#D2B48C] rounded-full mx-auto mb-6"></div>
      <p class="text-xl text-gray-300 max-w-3xl mx-auto">Discover our premium collection of authentic Sri Lankan handicrafts</p>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8 lg:gap-10">
      <?php
        $select_products = $conn->prepare("SELECT * FROM products ORDER BY id DESC LIMIT 6");
        $select_products->execute();
        if($select_products->rowCount() > 0){
          while($fetch_products = $select_products->fetch(PDO::FETCH_ASSOC)){
            $pid     = (int)$fetch_products['id'];
            $price   = (float)$fetch_products['price'];
            $qty     = (int)($fetch_products['quantity'] ?? 0);
            $inStock = $qty > 0;
      ?>
      <form action="" method="POST" class="group">
        <!-- hidden first so any submit button posts pid -->
        <input type="hidden" name="pid" value="<?= $pid; ?>">

        <div class="product-card p-6 relative h-full flex flex-col">
          <div class="absolute top-6 left-6 price-badge z-10">Rs <?= number_format($price, 2); ?></div>

          <div class="badge-stock">
            <?php if(!$inStock): ?>
              <span class="pill badge-oos text-xs px-2 py-1 rounded border">Out of stock</span>
            <?php elseif($qty < 10): ?>
              <span class="pill text-xs px-2 py-1 rounded border">Only <?= $qty; ?> left</span>
            <?php endif; ?>
          </div>

          <div class="card-actions">
            <button type="submit" name="add_to_wishlist" class="w-11 h-11 glass-effect rounded-full flex items-center justify-center hover:text-white hover:bg-gradient-to-r hover:from-[#8B4513] hover:to-[#D2B48C]" aria-label="Wishlist">
              <i class="fas fa-heart"></i>
            </button>
            <a href="view_page.php?pid=<?= $pid; ?>" class="w-11 h-11 glass-effect rounded-full flex items-center justify-center hover:text-white hover:bg-gradient-to-r hover:from-[#8B4513] hover:to-[#D2B48C]" aria-label="View">
              <i class="fas fa-eye"></i>
            </a>
          </div>

          <div class="aspect-square rounded-2xl overflow-hidden mb-6 relative">
            <img src="uploaded_img/<?= htmlspecialchars($fetch_products['image']); ?>" alt="<?= htmlspecialchars($fetch_products['name']); ?>" class="w-full h-full object-cover">
            <?php if(!$inStock): ?><div class="oos-overlay text-white text-lg rounded">OUT OF STOCK</div><?php endif; ?>
          </div>

          <div class="info-panel mt-auto space-y-4">
            <h3 class="text-xl product-title line-2"><?= htmlspecialchars($fetch_products['name']); ?></h3>

            <div class="flex items-center gap-3">
              <label class="text-sm font-medium">QTY:</label>
              <input type="number" min="1" value="<?= $inStock ? 1 : 0; ?>" name="p_qty"
                     class="qty w-24 px-3 py-2 glass-effect rounded-lg text-white text-center focus:ring-2 focus:ring-[rgb(139,69,19)]"
                     <?= $inStock ? '' : 'disabled'; ?>>
            </div>

            <button type="submit" name="add_to_cart"
                    class="w-full bg-gradient-to-r from-[#8B4513] to-[#D2B48C] text-white py-3.5 rounded-xl font-semibold hover-glow neon-glow transition"
                    <?= $inStock ? '' : 'disabled style="opacity:.6;cursor:not-allowed"'; ?>>
              <i class="fas fa-shopping-cart mr-2"></i> <?= $inStock ? 'ADD TO CART' : 'UNAVAILABLE' ?>
            </button>
          </div>
        </div>
      </form>
      <?php
          }
        }else{
          echo '<div class="col-span-full text-center py-16">
                  <div class="glass-effect p-12 rounded-3xl max-w-md mx-auto">
                    <i class="fas fa-box-open text-6xl" style="color:#CD853F)"></i>
                    <p class="text-2xl text-gray-300 font-medium">No products available yet!</p>
                  </div>
                </div>';
        }
      ?>
    </div>

    <div class="text-center mt-12">
      <a href="shop.php" class="inline-flex items-center bg-gradient-to-r from-[#5D4037] to-[#4E342E] glass-effect text-white px-8 py-4 rounded-full font-semibold text-lg hover-glow transition">
        <i class="fas fa-store mr-3"></i> VIEW ALL PRODUCTS <i class="fas fa-arrow-right ml-3"></i>
      </a>
    </div>
  </div>
</section>

<?php include 'about.php'; ?>

<?php include 'footer.php'; ?>
<script src="js/script.js"></script>
</body>
</html>
