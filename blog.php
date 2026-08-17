<?php
require_once 'db-connection/db_conn.php';

// Fetch all articles
$articlesQuery = "SELECT * FROM tbl_blog ORDER BY is_featured DESC, created_at DESC";
$articlesResult = $conn->query($articlesQuery);
$articles = [];
if ($articlesResult) {
    while ($row = $articlesResult->fetch_assoc()) {
        $articles[] = $row;
    }
}

// Separate featured article if exists
$featuredArticle = null;
foreach ($articles as $index => $article) {
    if ($article['is_featured']) {
        $featuredArticle = $article;
        break;
    }
}
if (!$featuredArticle && !empty($articles)) {
    $featuredArticle = $articles[0];
}

// Fetch unique categories
$categories = [];
$catRes = $conn->query("SELECT DISTINCT category FROM tbl_blog ORDER BY category ASC");
if ($catRes) {
    while ($cRow = $catRes->fetch_assoc()) {
        $categories[] = $cRow['category'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Medi-Care Health & Wellness Blog. Expert medical insights, preventive healthcare guidelines, cardiology advice, and neurology articles written by certified specialists.">
    <title>Health Blog & Medical Insights | Medi-Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- Animated Background -->
    <div class="bg-pattern"></div>

    <!-- ===== NAVBAR ===== -->
    <nav class="navbar" id="navbar">
        <a href="index.php" class="nav-brand">
            <div class="nav-brand-icon">M+</div>
            <div class="nav-brand-text">Medi-<span>Care</span></div>
        </a>

        <ul class="nav-links" id="navLinks">
            <li><a href="index.php" class="nav-link">Home</a></li>
            <li><a href="doctors.php" class="nav-link">Doctors</a></li>
            <li><a href="blog.php" class="nav-link active" style="color: var(--primary); font-weight: 700;">Blog</a></li>
            <li><a href="how_to_use.php" class="nav-link">How to Use</a></li>
            <li><a href="index.php#features" class="nav-link">Features</a></li>
            <li><a href="faq.php" class="nav-link">FAQ</a></li>
            <li><a href="privacy_policy.php" class="nav-link">Privacy</a></li>

            <!-- Login Dropdown -->
            <li class="nav-dropdown btn-nav-login" id="loginDropdown">
                <button class="dropdown-trigger" aria-expanded="false" aria-haspopup="true">
                    Login
                    <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
                <div class="dropdown-menu" role="menu">
                    <div class="dropdown-label">Login as</div>
                    <a href="doctor/login.php" class="dropdown-item" role="menuitem">
                        <div class="dropdown-item-icon doctor"><i class="fi fi-rr-stethoscope"></i></div>
                        <div class="dropdown-item-info">
                            <h4>Doctor</h4>
                            <p>Access your dashboard</p>
                        </div>
                    </a>
                    <a href="patient/login.php" class="dropdown-item" role="menuitem">
                        <div class="dropdown-item-icon patient"><i class="fi fi-rr-user"></i></div>
                        <div class="dropdown-item-info">
                            <h4>Patient</h4>
                            <p>View appointments & records</p>
                        </div>
                    </a>
                </div>
            </li>

            <!-- Register Dropdown -->
            <li class="nav-dropdown btn-nav-register" id="registerDropdown">
                <button class="dropdown-trigger" aria-expanded="false" aria-haspopup="true">
                    Register
                    <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
                <div class="dropdown-menu" role="menu">
                    <div class="dropdown-label">Register as</div>
                    <a href="doctor/register.php" class="dropdown-item" role="menuitem">
                        <div class="dropdown-item-icon doctor"><i class="fi fi-rr-stethoscope"></i></div>
                        <div class="dropdown-item-info">
                            <h4>Doctor</h4>
                            <p>Join our medical network</p>
                        </div>
                    </a>
                    <a href="patient/register.php" class="dropdown-item" role="menuitem">
                        <div class="dropdown-item-icon patient"><i class="fi fi-rr-user"></i></div>
                        <div class="dropdown-item-info">
                            <h4>Patient</h4>
                            <p>Create your health profile</p>
                        </div>
                    </a>
                </div>
            </li>
        </ul>

        <!-- Mobile Toggle -->
        <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle navigation">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </nav>

    <!-- ===== PAGE HEADER ===== -->
    <header class="blog-page-header">
        <div class="blog-badge">
            <i class="fi fi-rr-document"></i> Health &amp; Wellness Articles
        </div>
        <h1 class="doctors-page-title">
            Medical Insights &amp; <span class="gradient-text">Health News</span>
        </h1>
        <p class="doctors-page-subtitle">
            Stay informed with verified clinical articles, preventive health tips, and wellness guidelines from our medical specialists.
        </p>
    </header>

    <!-- ===== MAIN CONTAINER ===== -->
    <main class="blog-container">

        <!-- Featured Article Hero -->
        <?php if ($featuredArticle): ?>
            <div class="featured-blog-card" onclick="openArticleModal(<?php echo $featuredArticle['blog_id']; ?>)" style="cursor: pointer;">
                <div class="featured-blog-visual">
                    <span class="featured-pill-badge">⭐ Featured Editorial</span>
                    <div>
                        <span style="font-size: 0.85rem; background: rgba(0,0,0,0.4); padding: 4px 10px; border-radius: 20px;">
                            <?php echo htmlspecialchars($featuredArticle['category']); ?>
                        </span>
                    </div>
                </div>

                <div class="featured-blog-info">
                    <div class="featured-blog-meta">
                        <span><i class="fi fi-rr-calendar"></i> <?php echo date('M d, Y', strtotime($featuredArticle['created_at'])); ?></span>
                        <span>•</span>
                        <span><i class="fi fi-rr-clock"></i> <?php echo htmlspecialchars($featuredArticle['read_time']); ?></span>
                    </div>

                    <h2 class="featured-blog-title">
                        <?php echo htmlspecialchars($featuredArticle['title']); ?>
                    </h2>

                    <p class="featured-blog-excerpt">
                        <?php echo htmlspecialchars($featuredArticle['excerpt']); ?>
                    </p>

                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div class="blog-author-info">
                            <div class="blog-author-avatar">
                                <?php echo strtoupper(substr($featuredArticle['author_name'], 0, 2)); ?>
                            </div>
                            <div>
                                <div class="blog-author-name"><?php echo htmlspecialchars($featuredArticle['author_name']); ?></div>
                                <div class="blog-author-date"><?php echo htmlspecialchars($featuredArticle['author_role']); ?></div>
                            </div>
                        </div>

                        <button type="button" class="btn-read-article">
                            Read Story <i class="fi fi-rr-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Search & Category Filters -->
        <div class="blog-filter-toolbar">
            <div class="blog-search-box">
                <i class="fi fi-rr-search"></i>
                <input type="text" id="blogSearchInput" placeholder="Search health topics, keywords, symptoms, or doctor advice..." oninput="filterBlogArticles()">
            </div>

            <div class="blog-category-pills" id="blogCategoryPills">
                <button type="button" class="blog-pill active" onclick="setBlogCategory('all', this)">
                    All Topics
                </button>
                <?php foreach ($categories as $cat): ?>
                    <button type="button" class="blog-pill" onclick="setBlogCategory('<?php echo htmlspecialchars(strtolower($cat)); ?>', this)">
                        <?php echo htmlspecialchars($cat); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Articles Grid -->
        <div class="blog-grid" id="blogGrid">
            <?php foreach ($articles as $art): ?>
                <div class="blog-card" 
                     data-id="<?php echo $art['blog_id']; ?>" 
                     data-category="<?php echo htmlspecialchars(strtolower($art['category'])); ?>"
                     data-keywords="<?php echo htmlspecialchars(strtolower($art['title'] . ' ' . $art['excerpt'] . ' ' . $art['category'] . ' ' . $art['author_name'])); ?>">
                    
                    <div>
                        <div class="blog-card-header">
                            <span class="blog-card-category"><?php echo htmlspecialchars($art['category']); ?></span>
                            <span class="blog-card-readtime"><i class="fi fi-rr-clock"></i> <?php echo htmlspecialchars($art['read_time']); ?></span>
                        </div>

                        <h3 class="blog-card-title" onclick="openArticleModal(<?php echo $art['blog_id']; ?>)">
                            <?php echo htmlspecialchars($art['title']); ?>
                        </h3>

                        <p class="blog-card-excerpt">
                            <?php echo htmlspecialchars($art['excerpt']); ?>
                        </p>
                    </div>

                    <div class="blog-card-footer">
                        <div class="blog-author-info">
                            <div class="blog-author-avatar">
                                <?php echo strtoupper(substr($art['author_name'], 0, 2)); ?>
                            </div>
                            <div>
                                <div class="blog-author-name"><?php echo htmlspecialchars($art['author_name']); ?></div>
                                <div class="blog-author-date"><?php echo date('M d, Y', strtotime($art['created_at'])); ?></div>
                            </div>
                        </div>

                        <button type="button" class="btn-read-article" onclick="openArticleModal(<?php echo $art['blog_id']; ?>)">
                            Read <i class="fi fi-rr-arrow-right"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="noBlogMatch" style="display: none; text-align: center; padding: 50px 20px; color: var(--text-muted);">
            <i class="fi fi-rr-search" style="font-size: 2.5rem; display: block; margin-bottom: 10px;"></i>
            <h3 style="color: var(--text-primary); font-weight: 700; margin-bottom: 6px;">No Matching Articles Found</h3>
            <p style="font-size: 0.9rem; max-width: 420px; margin: 0 auto;">Try searching with different medical keywords or switch categories.</p>
        </div>

    </main>

    <!-- ===== ARTICLE READING MODAL ===== -->
    <div class="article-modal-backdrop" id="articleModal" onclick="handleModalBackdropClick(event)">
        <div class="article-modal-container">
            <div class="article-modal-header">
                <div class="article-modal-header-info">
                    <span id="modalCategory" class="blog-card-category"></span>
                    <span id="modalReadTime" style="font-size: 0.8rem; color: var(--text-muted);"></span>
                </div>
                <button type="button" class="article-modal-close" onclick="closeArticleModal()">&times;</button>
            </div>

            <div class="article-modal-body">
                <h1 id="modalTitle"></h1>
                <div style="font-size: 0.84rem; color: var(--text-muted); margin-bottom: 24px;" id="modalDate"></div>

                <div id="modalContent"></div>

                <div class="article-modal-author-card">
                    <div class="blog-author-avatar" id="modalAuthorAvatar" style="width: 44px; height: 44px; font-size: 1rem;"></div>
                    <div>
                        <div style="font-weight: 700; color: var(--text-primary);" id="modalAuthorName"></div>
                        <div style="font-size: 0.82rem; color: var(--text-secondary);" id="modalAuthorRole"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== FOOTER ===== -->
    <footer class="footer">
        <div class="footer-nav-links">
            <a href="index.php">Home</a>
            <a href="doctors.php">Doctors Directory</a>
            <a href="blog.php">Blog</a>
            <a href="how_to_use.php">How to Use</a>
            <a href="faq.php">FAQ</a>
            <a href="privacy_policy.php">Privacy & Policy</a>
            <a href="cookie_policy.php">Cookie Policy</a>
            <a href="report_bug.php">Report a Bug</a>
            <a href="patient/login.php">Patient Login</a>
            <a href="doctor/login.php">Doctor Login</a>
        </div>
        <p>&copy; <?php echo date('Y'); ?> Medi-Care Hospital Management System. All rights reserved.</p>
    </footer>

    <!-- ===== PASS DATA TO JS ===== -->
    <script>
        const articlesData = <?php echo json_encode(array_column($articles, null, 'blog_id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        let activeBlogCategory = 'all';

        function openArticleModal(id) {
            const article = articlesData[id];
            if (!article) return;

            document.getElementById('modalCategory').textContent = article.category;
            document.getElementById('modalReadTime').textContent = article.read_time;
            document.getElementById('modalTitle').textContent = article.title;
            document.getElementById('modalDate').textContent = 'Published on ' + new Date(article.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            document.getElementById('modalContent').innerHTML = article.content;
            document.getElementById('modalAuthorName').textContent = article.author_name;
            document.getElementById('modalAuthorRole').textContent = article.author_role;
            document.getElementById('modalAuthorAvatar').textContent = article.author_name.substring(0, 2).toUpperCase();

            const modal = document.getElementById('articleModal');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeArticleModal() {
            const modal = document.getElementById('articleModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        function handleModalBackdropClick(e) {
            if (e.target.id === 'articleModal') {
                closeArticleModal();
            }
        }

        // Filter Articles
        function filterBlogArticles() {
            const query = document.getElementById('blogSearchInput').value.toLowerCase().trim();
            const cards = document.querySelectorAll('.blog-card');
            let matchCount = 0;

            cards.forEach(card => {
                const category = card.getAttribute('data-category') || '';
                const keywords = card.getAttribute('data-keywords') || '';

                const matchesCat = (activeBlogCategory === 'all') || (category === activeBlogCategory);
                const matchesQuery = !query || keywords.includes(query);

                if (matchesCat && matchesQuery) {
                    card.style.display = 'flex';
                    matchCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            const noMatch = document.getElementById('noBlogMatch');
            if (matchCount === 0) {
                noMatch.style.display = 'block';
            } else {
                noMatch.style.display = 'none';
            }
        }

        function setBlogCategory(cat, btn) {
            activeBlogCategory = cat;
            document.querySelectorAll('.blog-pill').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            filterBlogArticles();
        }

        // ESC key to close modal
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeArticleModal();
        });

        // Navbar scroll effect
        const navbar = document.getElementById('navbar');
        window.addEventListener('scroll', () => {
            if (navbar) navbar.classList.toggle('scrolled', window.scrollY > 20);
        });

        // Mobile toggle
        const mobileToggle = document.getElementById('mobileToggle');
        const navLinks = document.getElementById('navLinks');
        if (mobileToggle && navLinks) {
            mobileToggle.addEventListener('click', () => {
                navLinks.classList.toggle('open');
                mobileToggle.classList.toggle('active');
            });
        }
    </script>
</body>
</html>
