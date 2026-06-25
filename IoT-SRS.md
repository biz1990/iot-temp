# Tài liệu Đặc tả Yêu cầu Phần mềm (SRS)
**Tên dự án:** Hệ thống Giám sát Nhiệt độ & Độ ẩm IoT  
**Phiên bản:** 2.0  
**Ngày tạo:** 25/06/2026  
**Người tạo:** [KSVINA-IT Team]  
**Ngôn ngữ:** Tiếng Việt  

---

## 1. Giới thiệu
### 1.1. Mục đích
Tài liệu này mô tả chi tiết các yêu cầu chức năng và phi chức năng của hệ thống giám sát nhiệt độ và độ ẩm. Hệ thống sử dụng thiết bị phần cứng Arduino R4 WiFi tích hợp cảm biến DHT22 và màn hình MKE-M07, giao tiếp với Backend PHP và cơ sở dữ liệu SQL Server trong môi trường mạng nội bộ (Intranet).

### 1.2. Phạm vi
*   Thu thập dữ liệu từ Arduino R4 WiFi (có cơ chế lưu đệm khi mất mạng và tự động đồng bộ lại).
*   Lưu trữ, quản lý, tự động xóa dữ liệu cũ (> 2 năm) trên SQL Server.
*   Giao diện web quản lý (Dashboard, Kanban, Cảnh báo, Phân quyền, Xuất báo cáo, OTA).
*   Hiển thị dữ liệu và cảnh báo trực tiếp trên thiết bị qua màn hình MKE-M07.

---

## 2. Mô tả tổng quan
### 2.1. Mục tiêu hệ thống
*   Giám sát thời gian thực (hoặc độ trễ thấp) nhiệt độ, độ ẩm.
*   Đảm bảo tính toàn vẹn dữ liệu ngay cả khi mạng nội bộ chập chờn.
*   Hỗ trợ cập nhật firmware từ xa (OTA) để giảm thiểu bảo trì phần cứng.

### 2.2. Kiến trúc tổng quan
*   **Thiết bị IoT (Edge):** Arduino R4 WiFi, cảm biến DHT22, màn hình OLED MKE-M07, còi/đèn cảnh báo.
*   **Backend:** PHP 8.x xử lý logic, API nhận dữ liệu, API OTA, Cronjob xử lý dữ liệu cũ.
*   **Database:** SQL Server lưu trữ dữ liệu.
*   **Frontend:** Web Interface (HTML, CSS, JS).
*   **Môi trường mạng:** Mạng nội bộ (LAN/WLAN), không yêu cầu HTTPS.

---

## 3. Yêu cầu chức năng (FR)

### 3.1. Quản lý Người dùng & Phân quyền
*   **FR-001:** Đăng nhập bằng username/password. Mật khẩu mã hóa bcrypt.
*   **FR-002:** Hỗ trợ "Remember me".
*   **FR-003:** Tự động đăng xuất (Session Timeout) sau 30 phút không thao tác.
*   **FR-004:** Chức năng Đăng xuất (Logout).
*   **FR-005:** Phân quyền: `Admin` (CRUD toàn bộ, cấu hình OTA) và `Viewer` (Chỉ xem Dashboard, xuất báo cáo).

### 3.2. Quản lý Thiết bị & Vị trí
*   **FR-006:** CRUD Thiết bị. Thông tin gồm: ID, Tên, Vị trí, Ngưỡng nhiệt/ẩm, `device_token`, `firmware_version`.
*   **FR-007:** CRUD Vị trí (Tòa nhà, Tầng, Phòng).
*   **FR-008:** Hệ thống tự động sinh `device_token` khi tạo thiết bị mới để Arduino xác thực.

### 3.3. Thu thập & Xử lý Dữ liệu từ Arduino (Edge Logic)
*   **FR-009:** Arduino đọc dữ liệu từ DHT22 và gửi về Server qua **HTTP POST** (JSON) mỗi 1 phút.
*   **FR-010 (Xử lý mất mạng & Buffer):** 
    *   Arduino tự động kiểm tra trạng thái WiFi. Nếu mất, tự động thực hiện quy trình reconnect.
    *   Nếu mất kết nối tới Server, Arduino lưu dữ liệu vào bộ nhớ đệm (Flash/EEPROM).
    *   Khi có mạng trở lại, Arduino gửi cộng dồn (batch sync) toàn bộ dữ liệu trong bộ nhớ đệm về Server.
*   **FR-011 (Validate dữ liệu):** Server kiểm tra dải đo vật lý của DHT22 (Nhiệt độ: -40°C đến 80°C, Độ ẩm: 0% đến 100%). Nếu dữ liệu ngoài dải này (lỗi cảm biến), Server đánh dấu `sensor_error` và không đưa vào biểu đồ.
*   **FR-012 (Trạng thái Offline):** Server tự động đánh dấu thiết bị `Offline` nếu không nhận được dữ liệu (kể cả dữ liệu sync) trong vòng 5 phút.

### 3.4. Hiển thị trên Thiết bị (MKE-M07 & Phần cứng)
*   **FR-013:** Màn hình MKE-M07 hiển thị: Nhiệt độ, Độ ẩm hiện tại, Trạng thái kết nối (WiFi/Server), và biểu tượng Pin/Mạng.
*   **FR-014:** Khi vượt ngưỡng, MKE-M07 hiển thị chữ "ALERT" nhấp nháy, đồng thời kích hoạt còi/đèn trên Arduino.
*   **FR-015:** Cơ chế Hysteresis (trễ) trên Arduino để tránh còi kêu liên tục khi nhiệt độ dao động quanh ngưỡng.

### 3.5. Dashboard & Kanban
*   **FR-016:** Dashboard hiển thị: Tổng thiết bị, Online/Offline, Số cảnh báo đang hoạt động (Active).
*   **FR-017:** Biểu đồ xu hướng, so sánh đa thiết bị (tối đa 5), lọc theo thời gian.
*   **FR-018 (Kanban):** 
    *   Hiển thị thiết bị theo cột: *Bình thường | Cảnh báo | Mất kết nối*.
    *   **Lưu ý logic:** Trạng thái này do hệ thống tự tính toán. Người dùng **KHÔNG** kéo thả để đổi trạng thái. Chỉ hỗ trợ kéo thả card để **chuyển đổi Vị trí/Nhóm** (nếu có).
*   **FR-019:** Quản lý Cảnh báo: Admin có thể bấm "Xác nhận/Đã xử lý" (Acknowledge) để tắt thông báo đỏ trên Dashboard.

### 3.6. Cập nhật Firmware từ xa (OTA)
*   **FR-020:** Admin tải lên file firmware (`.bin`) dành cho Arduino R4 WiFi.
*   **FR-021:** Hệ thống lưu file và cung cấp API để Arduino kiểm tra phiên bản mới.
*   **FR-022:** Arduino tự động tải file firmware qua HTTP, nạp vào vùng nhớ tạm và khởi động lại để áp dụng (có cơ chế rollback nếu nạp lỗi).

### 3.7. Xuất Dữ liệu & Báo cáo
*   **FR-023:** Chọn phạm vi (Ngày, Tuần, Tháng, Tùy chọn) và Thiết bị.
*   **FR-024:** Xuất CSV/Excel. Xuất PDF tổng hợp.
*   **FR-025:** Xử lý xuất file nền (Background job) nếu dữ liệu lớn.

### 3.8. Quản lý Hệ thống & Dữ liệu
*   **FR-026:** Audit Log ghi lại thao tác của Admin.
*   **FR-027 (Xóa dữ liệu cũ):** Hệ thống tự động chạy Cronjob hàng ngày để **xóa vĩnh viễn** các bản ghi trong bảng `sensor_data` có `timestamp` cũ hơn 2 năm.

---

## 4. Yêu cầu Phi chức năng (NFR)

### 4.1. Hiệu năng & Độ tin cậy
*   **NFR-001:** Dashboard phản hồi ≤ 2 giây. Hệ thống hỗ trợ ổn định **100 thiết bị** gửi dữ liệu đồng thời.
*   **NFR-002:** Arduino có cơ chế retry khi gửi HTTP thất bại (tối đa 3 lần trước khi ghi vào bộ nhớ đệm).
*   **NFR-003:** Database backup tự động hàng ngày. Uptime Server ≥ 99%.

### 4.2. Bảo mật (Môi trường nội bộ)
*   **NFR-004:** Do không dùng HTTPS, hệ thống bắt buộc **API nhận dữ liệu từ Arduino phải xác thực bằng `device_token`** kết hợp với **IP Whitelist** (chỉ chấp nhận IP của dải mạng nội bộ cấp cho thiết bị IoT).
*   **NFR-005:** Rate limiting cho API nhận dữ liệu để tránh nghẽn mạng nội bộ.

### 4.3. Khả năng sử dụng & Bảo trì
*   **NFR-006:** Giao diện Web tiếng Việt, Responsive.
*   **NFR-007:** Hỗ trợ OTA giúp giảm 80% thời gian bảo trì phần cứng.

---

## 5. Ràng buộc hệ thống

### 5.1. Phần cứng (Thiết bị IoT)
*   **Vi điều khiển:** Arduino R4 WiFi (Wi-Fi tích hợp, bộ nhớ Flash lớn hỗ trợ OTA và Buffer).
*   **Cảm biến:** DHT22 (Đo nhiệt độ và độ ẩm).
*   **Hiển thị:** Module MKE-M07 (Màn hình OLED I2C của MakerEdu).
*   **Phụ trợ:** Đèn LED, Còi buzzer, Nguồn cấp (Adapter 5V hoặc Pin).

### 5.2. Phần mềm & Hạ tầng
*   **Server:** CPU ≥ 4 cores, RAM ≥ 8GB.
*   **Web Server:** Apache/Nginx.
*   **Backend:** PHP 8.x.
*   **Database:** SQL Server 2019+.
*   **Môi trường mạng:** Mạng LAN/WLAN nội bộ, có DHCP Reservation (cố định IP) cho các Arduino.

---

## 6. Thiết kế Sơ bộ

### 6.1. Sơ đồ Cơ sở Dữ liệu (Cập nhật)
*   `users` (id, username, password_hash, role, last_login)
*   `locations` (id, name, description)
*   `devices` (id, name, location_id, device_token, ip_address, temp_threshold, humidity_threshold, firmware_version, last_seen, status)
*   `sensor_data` (id, device_id, temperature, humidity, is_synced_late, timestamp) *-> Có chỉ mục (index) theo timestamp để tăng tốc độ xóa dữ liệu cũ.*
*   `alerts` (id, device_id, type, value, threshold, status, created_at, resolved_at)
*   `firmware_versions` (id, version_number, file_path, release_notes, created_at)
*   `export_logs` (id, user_id, criteria, file_path, created_at)

### 6.2. Luồng dữ liệu & Xử lý Edge Case
1.  **Luồng bình thường:** DHT22 -> Arduino -> HTTP POST (kèm token) -> PHP API -> Validate -> SQL Server -> Cập nhật `last_seen`.
2.  **Luồng mất mạng:** Arduino phát hiện mất WiFi -> Reconnect -> Nếu không được -> Ghi dữ liệu vào Flash -> Khi có WiFi -> Gửi HTTP POST mảng dữ liệu (Batch) -> Server xử lý bình thường.
3.  **Luồng xóa dữ liệu:** SQL Server Agent / PHP Cronjob -> `DELETE FROM sensor_data WHERE timestamp < DATEADD(year, -2, GETDATE())`.

---

## 7. Phụ lục
### 7.1. Định nghĩa Thuật ngữ
*   **MKE-M07:** Module màn hình OLED I2C dùng để hiển thị thông tin tại chỗ.
*   **OTA (Over-The-Air):** Cập nhật firmware không dây.
*   **Buffer/Sync:** Cơ chế lưu đệm dữ liệu vào bộ nhớ thiết bị khi mất mạng và đồng bộ lại khi có mạng.
*   **Hysteresis:** Độ trễ ngưỡng (Ví dụ: Ngưỡng báo động là 30°C. Khi lên 30.5°C sẽ báo động, nhưng phải xuống dưới 29°C mới tắt báo động, tránh việc nhiệt độ dao động 29.9 - 30.1 làm còi kêu tắt/bật liên tục).

### 7.2. Giả định & Phụ thuộc
*   Mạng nội bộ ổn định, nếu có rớt mạng chỉ ở mức độ cục bộ và ngắn hạn.
*   Arduino R4 WiFi được cấp nguồn liên tục (không dùng chế độ Sleep sâu vì cần buffer và reconnect liên tục).
*   Dải đo của DHT22 là đủ chính xác cho môi trường giám sát thông thường (sai số ±0.5°C, ±2% RH).

---