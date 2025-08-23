<?php
http_response_code(404);
$pageTitle = "Page Not Found";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | SKMI Cloud</title>
    <style>
        .error-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            text-align: center;
            padding: 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .error-content {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 100%;
        }
        
        .error-code {
            font-size: 8rem;
            font-weight: bold;
            margin: 0;
            line-height: 1;
            color: #fff;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .error-title {
            font-size: 2rem;
            margin: 1rem 0;
            color: #fff;
        }
        
        .error-message {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
            opacity: 0.9;
        }
        
        .error-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #fff;
            color: #667eea;
        }
        
        .btn-primary:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .btn-secondary {
            background: transparent;
            color: #fff;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.5);
        }
        
        .search-box {
            margin: 2rem 0;
            position: relative;
            width: 100%;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.9);
            color: #333;
            font-size: 1rem;
        }
        
        .search-input:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
        }
        
        @media (max-width: 768px) {
            .error-code {
                font-size: 6rem;
            }
            
            .error-title {
                font-size: 1.5rem;
            }
            
            .error-content {
                padding: 2rem;
            }
            
            .error-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        .floating-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-content">
            <div class="floating-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            
            <h1 class="error-code">404</h1>
            <h2 class="error-title">Page Not Found</h2>
            
            <p class="error-message">
                The page you're looking for doesn't exist or has been moved. 
                Please check the URL or try searching for what you need.
            </p>
            
            <!-- <div class="search-box">
                <form action="/search" method="GET" style="display: flex; gap: 0.5rem;">
                    <input type="text" name="q" class="search-input" placeholder="Search files and folders..." 
                           value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                    <button type="submit" class="btn btn-primary" style="white-space: nowrap;">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div> -->
            
            <div class="error-actions">
                <a href="/v2" class="btn btn-primary">
                    <i class="fas fa-home"></i> Go Home
                </a>
                
                <a href="javascript:history.back()" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Go Back
                </a>
                
                <a href="/support" class="btn btn-secondary">
                    <i class="fas fa-life-ring"></i> Get Help
                </a>
            </div>
        </div>
    </div>

    <script>
        // Auto-focus search input
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('.search-input');
            if (searchInput) {
                searchInput.focus();
            }
        });
    </script>
</body>
</html>