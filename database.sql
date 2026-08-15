-- Database Initializer for Clash Arena – E-Sports Tournament Platform
-- Database: final_db

CREATE DATABASE IF NOT EXISTS `final_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `final_db`;

-- Drop existing tables in reverse order of foreign keys to avoid constraints issues
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `announcements`;
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `wallets`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `rewards`;
DROP TABLE IF EXISTS `leaderboard`;
DROP TABLE IF EXISTS `brackets`;
DROP TABLE IF EXISTS `matches`;
DROP TABLE IF EXISTS `team_members`;
DROP TABLE IF EXISTS `tournament_registrations`;
DROP TABLE IF EXISTS `tournaments`;
DROP TABLE IF EXISTS `teams`;
DROP TABLE IF EXISTS `games`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `super_admins`;
DROP TABLE IF EXISTS `admins`;
DROP TABLE IF EXISTS `users`;

-- 1. Create Users Table
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('superadmin', 'admin', 'customer') NOT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create Admins Table
CREATE TABLE `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `department` VARCHAR(100) DEFAULT 'Operations',
    `level` INT DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create Super Admins Table
CREATE TABLE `super_admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `access_level` VARCHAR(50) DEFAULT 'All Permissions',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create Roles Table
CREATE TABLE `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) UNIQUE NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create Permissions Table
CREATE TABLE `permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) UNIQUE NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Create Games Table
CREATE TABLE `games` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) UNIQUE NOT NULL,
    `banner_url` VARCHAR(255) DEFAULT '',
    `rules` TEXT,
    `entry_fee` DECIMAL(10, 2) DEFAULT 0.00,
    `prize_pool` DECIMAL(10, 2) DEFAULT 0.00,
    `max_teams` INT DEFAULT 16,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Create Teams Table
CREATE TABLE `teams` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) UNIQUE NOT NULL,
    `captain_id` INT NOT NULL,
    `logo_url` VARCHAR(255) DEFAULT '',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`captain_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Create Tournaments Table
CREATE TABLE `tournaments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `game_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `type` ENUM('solo', 'duo', 'squad', 'clan') DEFAULT 'solo',
    `status` ENUM('upcoming', 'registration_open', 'live', 'completed', 'cancelled') DEFAULT 'upcoming',
    `start_date` DATETIME NOT NULL,
    `max_participants` INT DEFAULT 16,
    `prize_pool` DECIMAL(10, 2) DEFAULT 0.00,
    `entry_fee` DECIMAL(10, 2) DEFAULT 0.00,
    `winner_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`game_id`) REFERENCES `games`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`winner_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Create Tournament Registrations Table
CREATE TABLE `tournament_registrations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tournament_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `team_id` INT DEFAULT NULL,
    `registration_type` ENUM('solo', 'team') NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tournament_id`) REFERENCES `tournaments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Create Team Members Table
CREATE TABLE `team_members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `team_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `role` ENUM('captain', 'member') DEFAULT 'member',
    `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `team_user` (`team_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Create Matches Table
CREATE TABLE `matches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tournament_id` INT NOT NULL,
    `round` INT DEFAULT 1,
    `match_order` INT DEFAULT 1,
    `player1_id` INT DEFAULT NULL,
    `player2_id` INT DEFAULT NULL,
    `team1_id` INT DEFAULT NULL,
    `team2_id` INT DEFAULT NULL,
    `score1` INT DEFAULT 0,
    `score2` INT DEFAULT 0,
    `winner_id` INT DEFAULT NULL,
    `winner_team_id` INT DEFAULT NULL,
    `status` ENUM('scheduled', 'live', 'completed', 'cancelled') DEFAULT 'scheduled',
    `scheduled_time` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tournament_id`) REFERENCES `tournaments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`player1_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`player2_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`team1_id`) REFERENCES `teams`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`team2_id`) REFERENCES `teams`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Create Brackets Table
CREATE TABLE `brackets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tournament_id` INT NOT NULL,
    `total_rounds` INT NOT NULL,
    `current_round` INT DEFAULT 1,
    `bracket_type` VARCHAR(50) DEFAULT 'single_elimination',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tournament_id`) REFERENCES `tournaments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Create Leaderboard Table
CREATE TABLE `leaderboard` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `team_id` INT DEFAULT NULL,
    `game_id` INT NOT NULL,
    `points` INT DEFAULT 0,
    `wins` INT DEFAULT 0,
    `kills` INT DEFAULT 0,
    `matches_played` INT DEFAULT 0,
    `win_rate` DECIMAL(5, 2) DEFAULT 0.00,
    `badge` VARCHAR(100) DEFAULT 'Novice',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`game_id`) REFERENCES `games`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Create Rewards Table
CREATE TABLE `rewards` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `badge_icon` VARCHAR(50) DEFAULT 'award',
    `type` ENUM('badge', 'coins', 'prize') NOT NULL,
    `value` DECIMAL(10, 2) DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Create Notifications Table
CREATE TABLE `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `type` VARCHAR(50) DEFAULT 'info',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Create Wallets Table
CREATE TABLE `wallets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `balance` DECIMAL(10, 2) DEFAULT 0.00,
    `coins` INT DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Create Transactions Table
CREATE TABLE `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `wallet_id` INT NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `type` ENUM('deposit', 'withdraw', 'entry_fee', 'reward') NOT NULL,
    `status` ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`wallet_id`) REFERENCES `wallets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. Create Announcements Table
CREATE TABLE `announcements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `created_by` INT NOT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. Create Settings Table
CREATE TABLE `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) UNIQUE NOT NULL,
    `setting_value` TEXT,
    `category` VARCHAR(50) DEFAULT 'general',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. Create Audit Logs Table
CREATE TABLE `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `action` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `details` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- SEED DATA
-- ==========================================

-- Seed default users (password for all: password123)
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`) VALUES
(1, 'Super Admin User', 'superadmin@app.com', '$2y$12$aLw4EBF9h.joff6ZBS8/z.Ev19Kp3eL9gUyrmRMQ1Mz.BWDdU2xcW', 'superadmin', 'active'),
(2, 'System Administrator', 'admin@app.com', '$2y$12$aLw4EBF9h.joff6ZBS8/z.Ev19Kp3eL9gUyrmRMQ1Mz.BWDdU2xcW', 'admin', 'active'),
(3, 'Premium Player', 'customer@app.com', '$2y$12$aLw4EBF9h.joff6ZBS8/z.Ev19Kp3eL9gUyrmRMQ1Mz.BWDdU2xcW', 'customer', 'active'),
(4, 'Shroud Gaming', 'shroud@clash.com', '$2y$12$aLw4EBF9h.joff6ZBS8/z.Ev19Kp3eL9gUyrmRMQ1Mz.BWDdU2xcW', 'customer', 'active'),
(5, 'S1mple Aim', 's1mple@clash.com', '$2y$12$aLw4EBF9h.joff6ZBS8/z.Ev19Kp3eL9gUyrmRMQ1Mz.BWDdU2xcW', 'customer', 'active'),
(6, 'Faker Mid', 'faker@clash.com', '$2y$12$aLw4EBF9h.joff6ZBS8/z.Ev19Kp3eL9gUyrmRMQ1Mz.BWDdU2xcW', 'customer', 'active'),
(7, 'TenZ Valor', 'tenz@clash.com', '$2y$12$aLw4EBF9h.joff6ZBS8/z.Ev19Kp3eL9gUyrmRMQ1Mz.BWDdU2xcW', 'customer', 'active'),
(8, 'Scump COD', 'scump@clash.com', '$2y$12$aLw4EBF9h.joff6ZBS8/z.Ev19Kp3eL9gUyrmRMQ1Mz.BWDdU2xcW', 'customer', 'active')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Seed Admins details
INSERT INTO `admins` (`user_id`, `department`, `level`) VALUES (2, 'E-Sports Operations', 2);

-- Seed Super Admins details
INSERT INTO `super_admins` (`user_id`, `access_level`) VALUES (1, 'Superuser Root Access');

INSERT INTO `games` (`id`, `name`, `slug`, `banner_url`, `rules`, `entry_fee`, `prize_pool`, `max_teams`) VALUES
(1, 'Valorant', 'valorant', 'https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&w=600&q=80', '5v5 Competitive Search & Destroy. Map pool: Bind, Haven, Split, Ascent. First to 13 wins.', 10.00, 5000.00, 8),
(2, 'Counter-Strike 2', 'cs2', 'https://images.unsplash.com/photo-1553481187-be93c21490a9?auto=format&fit=crop&w=600&q=80', '5v5 MR12 Matchmaking rules. Active duty maps only. Anti-cheat mandatory.', 15.00, 7500.00, 8),
(3, 'BGMI (Battlegrounds Mobile India)', 'bgmi', 'https://images.unsplash.com/photo-1511512578047-dfb367046420?auto=format&fit=crop&w=600&q=80', 'Squad Battle Royale. 4 matches (Erangel, Miramar, Sanhok, Erangel). Esports points system.', 5.00, 3000.00, 16),
(4, 'Free Fire MAX', 'freefire', 'https://images.unsplash.com/photo-1593305841991-05c297ba4575?auto=format&fit=crop&w=600&q=80', 'Squad Battle Royale. Bermudas and Kalahari map rotation. No emulator players allowed.', 2.00, 1500.00, 16),
(5, 'EA FC 24', 'eafc', 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?auto=format&fit=crop&w=600&q=80', '1v1 tournament. 6 minutes halves. Tactical defending mandatory. Standard team ratings.', 5.00, 1000.00, 16),
(6, 'Rocket League', 'rocketleague', 'https://images.unsplash.com/photo-1511919884226-fd3cad34687c?auto=format&fit=crop&w=600&q=80', '3v3 Standard mode. 5 minutes matches. Best of 3 single elimination.', 0.00, 500.00, 8),
(7, 'PUBG PC', 'pubgpc', 'https://images.unsplash.com/photo-1538481199705-c710c4e965fc?auto=format&fit=crop&w=600&q=80', 'Squad battle royale. FPP mode only. Standard competitive loot ratios.', 10.00, 4000.00, 8),
(8, 'Call of Duty Mobile', 'codm', 'https://images.unsplash.com/photo-1627856013091-fed6e4e30025?auto=format&fit=crop&w=600&q=80', '5v5 Multiplayer (Hardpoint, Search and Destroy). CoD Mobile World Championship rules.', 5.00, 2000.00, 8);

-- Seed Wallets for all seeded customers
INSERT INTO `wallets` (`user_id`, `balance`, `coins`) VALUES
(3, 500.00, 1200),
(4, 250.00, 600),
(5, 75.00, 320),
(6, 1200.00, 5400),
(7, 420.00, 2100),
(8, 0.00, 50);

-- Seed transactions
INSERT INTO `transactions` (`wallet_id`, `amount`, `type`, `status`, `description`) VALUES
(1, 500.00, 'deposit', 'completed', 'Direct credit card wallet reload'),
(1, -10.00, 'entry_fee', 'completed', 'Entry Fee for Valorant Showdown'),
(2, 250.00, 'deposit', 'completed', 'Wallet deposit via Paypal'),
(4, 1000.00, 'reward', 'completed', 'Grand Prize Winner payout for Apex Season 5'),
(5, 420.00, 'deposit', 'completed', 'Initial top-up');

-- Seed Teams
INSERT INTO `teams` (`id`, `name`, `captain_id`, `logo_url`) VALUES
(1, 'Sentinels Alpha', 3, 'sentinels.png'),
(2, 'NaVi Academy', 5, 'navi.png'),
(3, 'T1 Legends', 6, 't1.png'),
(4, 'Fnatic Storm', 7, 'fnatic.png');

-- Seed Team Members
INSERT INTO `team_members` (`team_id`, `user_id`, `role`) VALUES
(1, 3, 'captain'),
(1, 4, 'member'),
(2, 5, 'captain'),
(3, 6, 'captain'),
(4, 7, 'captain'),
(4, 8, 'member');

-- Seed Tournaments
INSERT INTO `tournaments` (`id`, `game_id`, `name`, `type`, `status`, `start_date`, `max_participants`, `prize_pool`, `entry_fee`, `winner_id`) VALUES
(1, 1, 'Valorant Champions Cup', 'squad', 'live', '2026-07-01 18:00:00', 8, 5000.00, 10.00, NULL),
(2, 2, 'CS2 Masters League', 'squad', 'registration_open', '2026-07-15 14:00:00', 8, 7500.00, 15.00, NULL),
(3, 5, 'EA FC Kickoff Tourney', 'solo', 'upcoming', '2026-07-20 12:00:00', 16, 1000.00, 5.00, NULL),
(4, 3, 'BGMI Clash Tour', 'squad', 'completed', '2026-06-25 15:00:00', 16, 3000.00, 5.00, 3);

-- Seed registrations
INSERT INTO `tournament_registrations` (`tournament_id`, `user_id`, `team_id`, `registration_type`, `status`) VALUES
(1, 3, 1, 'team', 'approved'),
(1, 5, 2, 'team', 'approved'),
(1, 6, 3, 'team', 'approved'),
(1, 7, 4, 'team', 'approved'),
(2, 3, 1, 'team', 'approved'),
(2, 5, 2, 'team', 'pending'),
(3, 3, NULL, 'solo', 'approved'),
(3, 4, NULL, 'solo', 'approved'),
(3, 5, NULL, 'solo', 'approved'),
(3, 6, NULL, 'solo', 'approved');

-- Seed matches for tournament 1 (Valorant Cup - approved 4 teams, Single Elimination Round 1 and Round 2)
INSERT INTO `matches` (`id`, `tournament_id`, `round`, `match_order`, `team1_id`, `team2_id`, `score1`, `score2`, `winner_id`, `winner_team_id`, `status`, `scheduled_time`) VALUES
(1, 1, 1, 1, 1, 2, 13, 9, 3, 1, 'completed', '2026-07-02 10:00:00'),
(2, 1, 1, 2, 3, 4, 11, 13, 7, 4, 'completed', '2026-07-02 12:00:00'),
(3, 1, 2, 1, 1, 4, 0, 0, NULL, NULL, 'scheduled', '2026-07-03 18:00:00');

-- Seed brackets
INSERT INTO `brackets` (`tournament_id`, `total_rounds`, `current_round`, `bracket_type`) VALUES
(1, 2, 2, 'single_elimination'),
(2, 3, 1, 'single_elimination');

-- Seed leaderboards
INSERT INTO `leaderboard` (`user_id`, `team_id`, `game_id`, `points`, `wins`, `kills`, `matches_played`, `win_rate`, `badge`) VALUES
(3, 1, 1, 1200, 24, 480, 30, 80.00, 'Champion'),
(4, NULL, 1, 850, 15, 310, 25, 60.00, 'Elite Player'),
(5, 2, 2, 1100, 22, 540, 28, 78.50, 'MVP'),
(6, 3, 5, 1400, 35, 110, 40, 87.50, 'Top Fragger'),
(7, 4, 1, 950, 18, 410, 24, 75.00, 'Elite Player');

-- Seed rewards
INSERT INTO `rewards` (`name`, `description`, `badge_icon`, `type`, `value`) VALUES
('Tournament Champion', 'Awarded for taking 1st place in any official bracket.', 'trophy', 'badge', 0.00),
('MVP Fragger', 'Awarded for securing high kill counts in league matches.', 'fire', 'badge', 0.00),
('Championship Bonus', 'Coins distribution for winning Valorant Showdown.', 'coins', 'coins', 500.00),
('Premium Entry Token', 'Entry token for invite-only leagues.', 'key', 'prize', 15.00);

-- Seed notifications
INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`) VALUES
(3, 'Welcome to Clash Arena!', 'Compete against elite players, create teams, check brackets, and win prize pools.', 'info'),
(3, 'Team Registration Approved', 'Your team "Sentinels Alpha" has been approved for the Valorant Champions Cup.', 'success'),
(3, 'Next Match Scheduled', 'Your next round match against "Fnatic Storm" is scheduled for tomorrow at 18:00.', 'warning'),
(4, 'Earnings Credited', 'Congratulations! 1000 Coins have been added to your wallet from rewards.', 'success');

-- Seed Announcements
INSERT INTO `announcements` (`title`, `content`, `created_by`, `status`) VALUES
('Clash Arena Grand Opening!', 'Welcome to the premium E-Sports platform. Registration for CS2 Masters and Valorant Champions is officially open! Claim your welcome coins in your profile wallet.', 1, 'active'),
('Server Maintenance Advisory', 'System updates will be pushed tonight at 02:00 AM. Uptime won''t be affected, but leaderboard calculation might delay up to 10 minutes.', 1, 'active');

-- Seed Settings
INSERT INTO `settings` (`setting_key`, `setting_value`, `category`) VALUES
('site_name', 'Clash Arena', 'general'),
('support_email', 'support@clasharena.com', 'general'),
('currency', 'USD', 'payment'),
('maintenance_mode', 'false', 'system'),
('meta_title', 'Clash Arena - E-Sports Tournament Platform', 'seo'),
('meta_desc', 'Join premium gaming leagues, form squad teams, review single-elimination brackets, and play for high prize pools.', 'seo');

-- Seed audit logs
INSERT INTO `audit_logs` (`user_id`, `action`, `ip_address`, `details`) VALUES
(1, 'System Initialization', '127.0.0.1', 'Database schemas generated and default system configurations set.'),
(3, 'User Registration Approved', '127.0.0.1', 'Approved registration for Sentinels Alpha team.'),
(2, 'Generated Tournament Bracket', '127.0.0.1', 'Single elimination bracket computed for Valorant Champions Cup.');
