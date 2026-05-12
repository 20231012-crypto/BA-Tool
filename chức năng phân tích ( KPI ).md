# Phân tích file `app.js` (BA Lead Report Dashboard)

File `app.js` chứa toàn bộ logic frontend cho ứng dụng **BA Lead Report Dashboard**. Ứng dụng này có chức năng tự động tải dữ liệu từ Google Sheets (dạng CSV), phân tích, lọc dữ liệu và vẽ các biểu đồ báo cáo cũng như các bảng chi tiết phục vụ cho mục đích theo dõi yêu cầu công việc của bộ phận BA (Business Analyst) và các nhân viên.

## 1. Cấu trúc chung của file

File có độ dài hơn 2400 dòng, được chia thành các phần chính (module) rõ ràng thông qua các comment phân chia:

1. **Constants (Hằng số):**
   - Chứa URL của Google Sheets CSV data (`GSHEET_CSV_URL`).
   - Cấu hình timezone (UTC+7).
   - Màu sắc (`COLORS`), cấu hình layout mặc định cho thư viện vẽ biểu đồ Plotly (`PLOTLY_LAYOUT_BASE`, `PLOTLY_CONFIG`).
   - Cấu hình màu cho các loại công việc đặc thù (`STABILITY_TYPES_CONFIG`, `BA_FULL_JOB_TYPES`).

2. **State (Trạng thái ứng dụng):**
   - Khai báo các biến lưu trữ toàn cục như `rawData` (dữ liệu thô tải từ file), `filteredData` (dữ liệu sau khi lọc), cấu hình bộ lọc (`activeFilters`, `reportMode`), trạng thái của các module (như `chartActiveModule` đang ở chế độ "employee" hay "ba").

3. **DOM References:**
   - Các hàm helper tắt (`$`, `$$`) và khai báo sẵn các thành phần HTML sẽ thao tác thường xuyên (khu vực loading, form lọc, tiêu đề).

4. **Initialization (Khởi tạo):**
   - Sự kiện `DOMContentLoaded` đính kèm các listener vào nút bấm, filter, window resize, thiết lập module navigation, và khởi chạy tự động hàm `fetchGoogleSheetData()`.

5. **Data Fetching & Parsing (Tải và phân tích dữ liệu):**
   - `fetchGoogleSheetData()`: Dùng Fetch API tải dữ liệu CSV.
   - `processWorkbook(workbook)`: Dùng thư viện `XLSX` (SheetJS) để chuẩn hóa tên cột, ánh xạ các trường dữ liệu động thành đối tượng có thuộc tính thống nhất (ví dụ: `request_created_at`, `status_group`, `delay_hours`), lọc ra dòng tiêu đề đúng. Đồng thời tính toán các thông tin bổ trợ như check "hoàn thành" hay chưa.
   - Các hàm helper xử lý kiểu dữ liệu tĩnh (`cleanStr`, `parseDate`, `parseNum`).

6. **Filtering Logic (Hệ thống lọc):**
   - `applyFilters()`: Lọc theo thời gian (từ ngày - đến ngày), lọc theo checkbox filter (chips), và tìm kiếm văn bản động ở các cột cụ thể.
   - Hàm `applyAndRender()` là trung tâm luồng dữ liệu (hub): Áp dụng các bộ lọc, sau đó tùy vào `chartActiveModule` mà gọi các hàm render UI phù hợp.

7. **Chart Rendering Functions (Logic vẽ biểu đồ bằng Plotly):**
   - **General / Employee Dashboard:**
     - `renderTrendComparison`: Biểu đồ đường (Line) thể hiện xu hướng phát sinh các loại yêu cầu theo thời gian (Hỗ trợ lọc local theo phân loại và loại yêu cầu).
     - `renderStabilityAnalysisChart`: Biểu đồ đường + đường trung bình động thể hiện mức độ duy trì bảo trì hệ thống (Có tùy chọn kỳ trung bình 3, 5, hoặc 8 tuần).
     - `renderAreaChart`: Area chart dạng 100% tỷ trọng các loại yêu cầu.
     - `renderPieChart`: Biểu đồ tròn cơ cấu phòng ban.
     - `renderComboChart`: Cột + đường để so sánh YC phát sinh với YC đã xử lý.
   - **BA Dashboard (`renderBaDashboard` & `drawBaWidgets`):**
     - **Scorecards:** 5 thẻ chỉ số gồm: Tổng số yêu cầu (Tuần này/Tuần cũ), Tỷ lệ hoàn thành, YC hoàn thành, YC chưa hoàn thành, Tổng giờ code. Các chỉ số này được chia tách chi tiết theo đơn vị (DION vs FFM).
     - **Biểu đồ:** Donut (tỷ trọng thời gian code, có hỗ trợ click để lọc chéo), Stacked Bar chart ngang (theo hệ thống), biểu đồ Trend (của Dev/BA theo tuần kèm các thẻ thống kê delta so với kỳ trước), 100% Stacked Area chart.

8. **Tables & Metric Cards (Các bảng chi tiết và thẻ số liệu):**
   - `renderSystemTable`: Tóm tắt tỷ lệ các loại yêu cầu theo từng Hệ thống.
   - `renderDetailTable`: Bảng chứa danh sách tất cả các yêu cầu (có ô search text nội bộ).
   - `renderBaDetailTable`: Bảng chi tiết cho BA, phân loại các task thuộc "Tuần này" hay "Tuần cũ".

9. **UI & Utilities (Giao diện & Hàm tiện ích):**
   - **Giao diện tổng quan:** Nút bật/tắt hiển thị nhãn số liệu trên biểu đồ (Toggle Labels), nút Print PDF tự động căn chỉnh lại biểu đồ khi in (`prepareAndPrint`).
   - **Hệ thống lọc nâng cao:** Bộ lọc động chọn 1 cột cụ thể để tìm kiếm từ khóa, cùng hàng loạt bộ lọc Sidebar (Hệ thống, Phòng ban, Người YC, Loại YC, Trạng thái, v.v.).
   - Format ngày tháng (`fmtDate`, `isoDate`), lấy tuần ISO (`getISOWeek`).
   - Hàm escape HTML chống XSS, hàm đổi Hex sang RGBA.
   - Tính toán giờ làm việc hành chính (`calcBusinessHours` trừ thứ 7, chủ nhật và giờ ngoài khoảng 8:00-16:00, có toggle bật/tắt trên UI).
   - Đồng hồ thời gian thực (Vietnam Realtime Clock UTC+7).

## 2. Phân tích Luồng Logic (Logic Flow)

1. **Lúc khởi động:** 
   - Hàm `fetchGoogleSheetData()` tự động được gọi. Hệ thống hiển thị hiệu ứng Loading.
2. **Xử lý Data thô:**
   - Khi nhận file CSV, `processWorkbook` dò tìm hàng nào là header (chứa "Mã yêu cầu" hoặc tương tự), rồi tiến hành build lên mảng object `rawData`. 
   - Quá trình này tự chuẩn hoá các format dữ liệu không nhất quán từ Excel (nhất là format Datetime).
3. **Xây dựng Menu/Dropdown Filter:**
   - Hệ thống tự đọc `rawData` và trích xuất danh sách duy nhất (Unique) các loại hệ thống, phòng ban, tính năng... để điền vào filter box (chips) và các options lựa chọn.
4. **Trigger thay đổi:**
   - Bất cứ khi nào User tương tác (đổi tab giữa Nhóm Employee/BA, click bộ lọc, tìm kiếm, đổi chế độ Tuần/Tháng): sự kiện đó gọi hàm `applyAndRender()`.
5. **Re-rendering (Render lại UI):**
   - Gọi `applyFilters()` trả về tập dữ liệu đã lọc (`filteredData`).
   - Tính toán lại toán bộ metric (giờ làm, tổng số lượng).
   - Truyền tập dữ liệu này xuống các hàm Render Plotly và Render Table để thay đổi giao diện real-time.

## 3. Các đặc điểm kỹ thuật chú ý
- **Phụ thuộc bên ngoài:** Code phụ thuộc vào thư viện `XLSX` (SheetJS) để đọc workbook và `Plotly` để render biểu đồ.
- **Tính toán Local:** Không sử dụng Database hay Backend API. Google Sheets đóng vai trò Database, toàn bộ logic join, query, filter được viết thẳng bằng Javascript phía Client.
- **Module Hóa (Ảo):** Hệ thống có 2 Module Dashboard riêng biệt ("Employee" và "BA"). Code phân luồng `if (chartActiveModule === 'ba')` để đổi view một cách linh động, ẩn/hiện các div thay vì nhảy trang.
- **Business Hours:** Tính năng cực kì đặc thù là hàm `calcBusinessHours()` giúp tính ra độ trễ (delay) hoặc thời gian code thực tế bằng cách bóc tách khung giờ 8h/ngày và T2-T6 ra khỏi mốc thời gian Start/End.
