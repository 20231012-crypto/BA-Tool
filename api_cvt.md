1	CVT_TEST
1.1	Công việc thường
Bao gồm 1 API:
- Danh sách công việc thường. GET: https://kinkin.1office.vn/api/work/normal/gets

1.1.1	Danh sách công việc thường
Method: GET
Url: https://kinkin.1office.vn/api/work/normal/gets
Access Token: 38216884069fc087ec353d333939552
Params:
Field	Type	Required	Description
access_token*	string	true	Mã bảo mật
limit	integer	false	Số bản ghi trên trang (mặc định 50, tối đa 100)
page	integer	false	Số trang (mặc định 1)
sort_by	string	false	Tên trường cần sắp xếp
sort_type	string	false	Thứ tự sắp xếp của tên trường cần sắp xếp, giá trị là 'asc' hoặc 'desc'. trong đó 'asc' là sắp xếp tăng dần. 'desc' là sắp xếp giảm dần
filters	json	false	Giá trị cần lọc
Là JSON ENDCODE của mảng dữ liệu. Ví dụ: [{"key1":"value1", "key2":"value2"},{"key1":"value1", "key2":"value2"},... ]
Cấu trúc của field filters
s	string	false	Từ khóa tìm kiếm
status	string	false	Trạng thái
Thuộc 1 trong các giá trị sau:(Sử dụng cột Giá trị, nếu cấu hình field_raws hãy sử dụng cột ID) IDGiá trịPENDING'Đang chờ'Mặc địnhDOING'Đang thực hiện'REVIEW'Đang đánh giá'COMPLETED'Hoàn thành'NOT_COMPLETED'Chưa hoàn thành'FAIL'Không hoàn thành'PAUSE'Tạm dừng'CANCEL'Hủy'EXPECTED'Dự kiến'CLOSED'Đã đóng'
project_id	string	false	Dự án
Nhập: Mã dự án
assign_ids*	string comma	true	Thực hiện
Là 1 chuỗi mã hoặc tên nhân sự được phân cách bởi dấu phẩy. Ví dụ: 'NV06,NV08,Nguyễn Văn C'
follower_ids	string comma	false	Theo dõi/phối hợp thực hiện
Là 1 chuỗi mã hoặc tên nhân sự được phân cách bởi dấu phẩy. Ví dụ: 'NV06,NV08,Nguyễn Văn C'
start_plan*	date	true	Bắt đầu dự kiến
Định dạng dd/mm/YYYY
end_plan*	date	true	Kết thúc dự kiến
Định dạng dd/mm/YYYY
rating_point	string	false	Kết quả đánh giá
Thuộc 1 trong các giá trị sau:(Sử dụng cột Giá trị, nếu cấu hình field_raws hãy sử dụng cột ID) IDGiá trị1'Kém'2'Trung bình'3'Khá'4'Tốt'5'Xuất sắc'
rating_score	real	false	Điểm đánh giá
rating_percent	real	false	Đánh giá tiến độ
rating_desc	html	false	Ý kiến đánh giá
is_assign_hour	string	false	Giao việc theo giờ
Thuộc 1 trong các giá trị sau:(Sử dụng cột Giá trị, nếu cấu hình field_raws hãy sử dụng cột ID) IDGiá trị0'Không'Mặc định1'Có'
no_allow_file_on_success	string	false	Cho phép thảo luận và tải file đính kèm khi công việc đã hoàn thành
Thuộc 1 trong các giá trị sau:(Sử dụng cột Giá trị, nếu cấu hình field_raws hãy sử dụng cột ID) IDGiá trịdefault'Biến động theo cài đặt'no'Cho phép'yes'Không cho phép'Mặc định
start	date	false	Bắt đầu thực tế
Định dạng dd/mm/YYYY
end	date	false	Kết thúc thực tế
Định dạng dd/mm/YYYY
out_date_completed	integer	false	Số ngày hoàn thành quá hạn
schedule_compliance	real	false	Hệ số tiến độ
priority*	string	true	Ưu tiên
Thuộc 1 trong các giá trị sau:(Sử dụng cột Giá trị, nếu cấu hình field_raws hãy sử dụng cột ID) IDGiá trị1'Cao'14'Trung bình'17'Bình thường'16'Thấp'
date_created	date	false	Ngày tạo
Định dạng dd/mm/YYYY
date_updated	date	false	Ngày cập nhật
Định dạng dd/mm/YYYY
desc	html	false	Mô tả
percent	string	false	Tiến độ
Thuộc 1 trong các giá trị sau:(Sử dụng cột Giá trị, nếu cấu hình field_raws hãy sử dụng cột ID) IDGiá trị0'0 %'Mặc định10'10 %'20'20 %'30'30 %'40'40 %'50'50 %'60'60 %'70'70 %'80'80 %'90'90 %'100'100 %'
parent_id	integer	false	Công việc cha
type_id*	string	true	Loại công việc
Thuộc 1 trong các giá trị sau:(Sử dụng cột Giá trị, nếu cấu hình field_raws hãy sử dụng cột ID) IDGiá trị4'1. Không Gấp - Không Quan trọng'3'2. Gấp - Không Quan trọng'2'3. Không Gấp - Quan trọng'1'4. Gấp - Quan trọng'
is_sub	string	false	Công việc con
Thuộc 1 trong các giá trị sau:(Sử dụng cột Giá trị, nếu cấu hình field_raws hãy sử dụng cột ID) IDGiá trị0'Không'Mặc định1'Có'
deep_level	string	false	Cấp công việc
Thuộc 1 trong các giá trị sau:(Sử dụng cột Giá trị, nếu cấu hình field_raws hãy sử dụng cột ID) IDGiá trị0'Cấp 0 (Không có công việc cha)'Mặc định1'Cấp 1'2'Cấp 2'3'Cấp 3'4'Cấp 4'5'Cấp 5'
process_type_id	string	false	Quy trình
Thuộc 1 trong các giá trị sau:(Sử dụng cột Giá trị, nếu cấu hình field_raws hãy sử dụng cột ID) IDGiá trịIDGiá trị133'(SỰ CỐ MỚI KHÔNG BƠI VÀO ĐÂY)_Quy trình xử lý sự cố hàng hóa Nhật Việt Update 2606'169'AUTOMATE_XLSC'111'AUTOMATION_Tạo Tài Khoản Cho Nhân Sự Mới'74'BACKUP - QUY TRÌNH XỬ LÝ SỰ CỐ'72'BACKUP QUY TRÌNH THANH TOÁN'107'Bảng tổng hợp KPI cho NV CSKH'106'Bảng tổng hợp KPI cho TP CSKH'103'Bảng tổng hợp KPI cho TP HCNS'104'Bảng tổng hợp KPI tháng cho nhân viên phòng HCNS'18'Báo cáo Hàng ngày công việc ngày hôm sau'175'BG_04 - v2.1'238'Cảnh báo dữ liệu đơn hàng mua bị xóa'214'Cảnh báo xóa dữ liệu import'15'Cập nhật Hóa đơn mua hàng ( Đoàn Thường )'165'CRM_Quy Trình Báo Giá'23'CSKH - QUY TRÌNH XỬ LÝ SỰ CỐ'60'CSKH - QUY TRÌNH XỬ LÝ SỰ CỐ - Hàng Việt (Test biểu mẫu)'63'CSKH - QUY TRÌNH XỬ LÝ SỰ CỐ CHỨNG TỪ THUẾ'75'CSKH - Quy trình xử lý sự cố hàng hóa Nhật Việt'44'DEMO QUY TRÌNH THANH TOÁN'45'demo QUY TRÌNH ĐỀ NGHỊ MUA HÀNG DỊCH VỤ - KHÔNG LIÊN HỆ NCC'89'Duplicate KT02 Test'182'Duyệt thay đổi thông số KPI qua API'109'Kinh Doanh Đề Xuất'205'Kinkin-HRM-Notification'51'Ký duyệt liên quan đến nhân sự'17'Ký duyệt thông báo, văn bản,...'37'Ký duyệt thông báo, văn bản,... (Chỉ GĐ ký)'46'Ký duyệt thông báo, văn bản,... (TBP và GĐ ký)'77'Mẫu Hồ sơ khách hàng xin đề xuất ( Thiếu form Word)'27'MKT Quy trình thiết kế Marketing ( Test)'139'PA2_Quy Trình Đề Xuất Mua Vật Tư Phục Vụ Vận Hành cho Trưởng bộ phận'183'Phiếu thông báo sự cố IT'145'Quy Trình báo cáo công việc theo tuần'31'QUY TRÌNH BÁO CÁO SẢN LƯỢNG HÀNG VỀ KHO'130'Quy Trình báo cáo Sự Cố Kỹ Thuật IT'120'Quy Trình Báo Cáo Thị Trường Theo Tuần'126'Quy trình báo giá khách hàng'129'Quy trình báo giá khách hàng (Mẫu của Mr. Đức)'131'Quy Trình Báo Giá Phòng Kinh Doanh'146'Quy trình cấp phát tài sản cho CBNV'156'Quy trình cấp phát tài sản cho CBNV - AUTOMATION'53'Quy trình chào mừng nhân sự mới'119'Quy Trình Duyệt Bảng Lương Q3/2024'125'Quy trình duyệt hiệu quả công việc nhân sự phòng TCKT'211'Quy trình duyệt hồ sơ ứng viên'2'QUY TRÌNH GIẢI QUYẾT NGHỈ VIỆC'61'Quy trình giải quyết sự cố'42'Quy trình Giao việc - Nhận việc - Triển khai công việc'67'Quy trình hàng Việt Nam xuất Nhật Bản (HAN - JP)'62'QUY TRÌNH HOÀN THUẾ'64'QUY TRÌNH HOÀN THUẾ KH 093'20'Quy trình Hoàn tiền : Hàng đồng chất'47'QUY TRÌNH HOÀN TIỀN CHUYỂN NHẦM CHO CBNV'10'QUY TRÌNH HOÀN TIỀN CHUYỂN NHẦM CHO KHÁCH'48'QUY TRÌNH HOÀN TIỀN CHUYỂN NHẦM CHO KHÁCH'80'QUY TRÌNH HOÀN TIỀN CƯỚC VẬN CHUYỂN - CS MỚI'16'Quy trình hoàn ứng ( Cập nhật 20240301)'148'QUY TRÌNH KÝ SỐ (Hàng Cũ. Hồ Sơ mới không bơi vào đây)'178'QUY TRÌNH KÝ SỐ VÀ KÝ DUYỆT VĂN BẢN V2.0 (Dành cho Hồ Sơ Mới)'159'Quy trình luân chuyển tài sản cho CBNV'13'Quy trình nhắc chấm công ra'11'Quy trình nhắc chấm công vào'121'Quy trình phát triển hệ thống phần mềm ( Kin Kin Logistics)'69'Quy trình Sáng tạo nội dung - Đăng bài - Sự chuyên nghiệp'6'QUY TRÌNH TẠM ỨNG'73'QUY TRÌNH TẠM ỨNG'190'QUY TRÌNH TẠM ỨNG -HOÀN ỨNG 1401'134'Quy Trình tạo mã khách hàng và tài khoản khách hàng'143'Quy Trình tạo mã khách hàng và tài khoản khách hàng Update 1007'71'QUY TRÌNH THANH TOÁN ( TEST)'43'Quy trình Thanh toán Nhà cung cấp ( Phòng Thông quan)'68'Quy trình thay đổi Báo Giá tới CBNV ( Sếp yêu cầu)'19'Quy trình thiết kế Marketing'141'QUY TRÌNH THÔI VIỆC CHO NHÂN SỰ'220'QUY TRÌNH THÔI VIỆC CHO NHÂN SỰ'147'Quy trình thu hồi tài sản'157'Quy trình thu hồi tài sản cho CBNV - AUTOMATION'235'Quy trình thu hồi tài sản TEST'101'Quy trình Tính lương sản lượng Phòng CSKH (NV)'97'Quy trình Tính lương sản lượng Phòng Kinh Doanh(NV)'102'Quy trình Tổng Tính lương sản lượng Phòng CSKH(QL)'99'Quy trình Tổng Tính lương sản lượng Phòng Kinh Doanh(QL)'108'Quy trình Tổng Tính lương sản lượng Phòng Thông Quan'55'Quy trình tự động gửi email cho ứng viên'241'Quy trình tự động gửi email cho ứng viên'54'Quy trình tự động gửi email chúc mừng sinh nhật CBNV'254'Quy trình tự động gửi email chúc mừng sinh nhật CBNV'3'QUY TRÌNH XÉT DUYỆT ORDER, CẤP PHÁT VPP'186'Quy trình xin nghỉ phép [Test]'70'Quy trình xử lí hàng Back'164'Quy trình Xử lý Công Tác và Đăng ký đi công tác'4'QUY TRÌNH XỬ LÝ DỮ LIỆU PHÒNG DATA'163'Quy trình xử lý PHÚC CÔNG'56'QUY TRÌNH XỬ LÝ SỰ CỐ - HÀNG VIỆT NAM ĐI'170'Quy trình xử lý sự cố hàng hóa Nhật Việt Update 0904'142'Quy trình xử lý sự cố hàng hóa Nhật Việt Update 0907'118'Quy trình xử lý sự cố hàng hóa theo tuyến Nhật Việt'168'Quy trình xử lý sự cố HOÀN HÀNG'128'Quy Trình xử lý sự cố IT tại văn phòng'26'Quy trình Yêu cầu Sếp hỗ trợ'181'Quy trình đăng ký KPI Cá Nhân phòng Kinh Doanh'185'Quy trình đăng ký/Thông Báo Dịch vụ mới'1'QUY TRÌNH ĐÁNH GIÁ - KÝ HĐ'58'Quy trình đánh giá hết hạn HĐLĐ'244'Quy trình đánh giá hết hạn HĐLĐ - HCM'140'Quy Trình đánh giá Nhân Sự Thử Việc'154'Quy Trình đánh giá Nhân Sự Thử Việc'14'QUY TRÌNH ĐÀO TẠO'41'QUY TRÌNH ĐỀ NGHỊ MUA HÀNG DỊCH VỤ - KHÔNG LIÊN HỆ NCC'9'QUY TRÌNH ĐỀ NGHỊ MUA HÀNG DỊCH VỤ - LIÊN HỆ NCC'7'Quy trình Đề nghị Thanh toán'100'Quy trình đề nghị thanh toán NCC'21'Quy trình Đề xuất'30'Quy trình Đề xuất CBNV'82'Quy trình Đề xuất Giải quyết công việc - Mua sắm vật tư (HCNS duyệt)'127'Quy trình Đề xuất Giải quyết công việc - Mua sắm vật tư chi nhánh HCM'39'Quy trình Đề xuất giảm giá cho KH'124'Quy Trình Đề Xuất Mua Vật Tư Bộ phận kho'166'Quy Trình Đề Xuất Mua Vật Tư Bộ phận kho'135'Quy Trình Đề Xuất Mua Vật Tư Phục Vụ Vận Hành'136'Quy Trình Đề Xuất Mua Vật Tư Phục Vụ Vận Hành'123'Quy trình Đề xuất tạo tài khoản Bổ Sung'78'Quy trình Đề xuất tạo tài khoản CBNV'87'test'96'test'196'TEST - Quy trình tiếp nhận nhân sự mới'84'Test - Quy trình Tính lương sản lượng Phòng Kinh Doanh Nhật Việt'83'Test Quy trình - Đoàn thường'85'Test Sa thải'110'Thông Báo Tạo Tài Khoản Cho Nhân Sự Mới'162'Thu thập dữ liệu máy chấm công'93'Tổng hợp hiệu quả công việc'50'Xử lý hàng hóa'65'Xử lý sự cố hàng giao thẳng đối tác'79'Yêu cầu hỗ trợ thông tin tại Nhật Bản - Hàng Việt xuất Nhật'122'Yêu Cầu Tạo Tài Khoản Cho Nhân Sự'218'Yêu Cầu TẠO/CHỈNH SỬA Tài Khoản Cho Nhân Sự'144'Yêu Cầu TẠO/CHỈNH SỬA Tài Khoản Cho Nhân Sự ( ĐÃ NGỪNG HOẠT ĐỘNG YÊU CẦU SANG IT_ACC_CRE_02 )'171'[CRM THỬ NGHIỆM]_Quy Trình Báo Giá Phòng Kinh Doanh'81'[GTV] Quy trình chào mừng nhân sự mới'155'[GTV] Quy trình công bố, đăng bài viết cho văn bản'167'[Microsoft API]_Xử lý sự cố tự động'113'[TEST02]_Quy trình đề nghị thanh toán NCC'114'[TEST02]_Quy trình đề nghị thanh toán NCC'112'[TEST]_Quy trình đề nghị thanh toán NCC'161'[Thanh Toán]_Quy trình đề nghị TẠM ỨNG và THANH TOÁN LƯƠNG'116'[Thanh Toán]_Quy trình đề nghị thanh toán'193'[Thanh Toán]_Quy trình đề nghị thanh toán 2022502'150'[Thanh Toán]_Quy trình đề nghị thanh toán 2207'115'[Thanh Toán]_Quy trình đề nghị thanh toán NCC'117'[Thanh Toán]_Quy trình đề nghị thanh toán NCC qua Danka_ĐANG CHẠY THỬ NGHIỆM'229'Đánh giá Nhân Sự Thử Việc - Hà Nội'232'Đánh giá Nhân Sự Thử Việc - Hà Nội'199'Đánh giá Nhân Sự Thử Việc - HCM'251'Đánh giá Nhân Sự Thử Việc - HCM - Test'36'ĐỀ NGHỊ ỨNG LƯƠNG'105'Đề xuất hỗ trợ'24'Đề xuất lương sản lượng của Nhân viên'49'ĐỀ XUẤT THƯỞNG LƯƠNG SẢN LƯỢNG KHO'33'ĐỀ XUẤT THƯỞNG LƯƠNG SẢN LƯỢNG KHO+ KẾ TOÁN'28'ĐỀ XUẤT THƯỞNG LƯƠNG SẢN LƯỢNG NHÂN VIÊN'29'ĐỀ XUẤT THƯỞNG LƯƠNG SẢN LƯỢNG PHÒNG CSKH'32'ĐỀ XUẤT THƯỞNG LƯƠNG SẢN LƯỢNG PHÒNG SALE'149'Điều kiện chủ động Quy Trình báo cáo công việc theo tuần'
owner_ids*	string comma	true	Giao việc
Là 1 chuỗi mã hoặc tên nhân sự được phân cách bởi dấu phẩy. Ví dụ: 'NV06,NV08,Nguyễn Văn C'
owner_id	string comma	false	Người quản lý
Là 1 chuỗi mã hoặc tên nhân sự được phân cách bởi dấu phẩy. Ví dụ: 'NV06,NV08,Nguyễn Văn C'
tasklist_id	string	false	Danh mục công việc
Thuộc 1 trong các giá trị sau:(Sử dụng cột Giá trị, nếu cấu hình field_raws hãy sử dụng cột ID) IDGiá trịIDGiá trị6'1. Thống kê các trường cần thiết có trong website'7'2. Tạo Demo giao diện WebSite'8'3. Chạy thử nghiệm, căn chỉnh bố cục'9'4. Kiểm tra nội dung trên trang web'10'5. Chạy Lần cuối trước khi đệ trình lên CEO'44'Báo cáo định kỳ theo Tháng'48'Báo cáo định kỳ theo Tháng / Phòng ban'50'Báo cáo Định kỳ theo Tháng của Phòng / Nhóm'52'Báo cáo Định kỳ theo Tháng của Trưởng bộ phận'43'Báo cáo Định kỳ theo tuần'47'Báo cáo định kỳ theo tuần / Phòng ban'49'Báo cáo định kỳ theo Tuần của Phòng/ Nhóm'51'Báo cáo Định kỳ theo Tuần của Trưởng bộ phận'36'Báo cáo, Giải trình'16'Cập nhật, mở rộng và hoàn thiện tính năng'53'Chăm sóc khách hàng'35'Chi nhánh Hồ Chí Minh'37'Chi nhánh Nhật Bản'31'Dữ liệu'15'Đưa vào Close Beta Thử Nghiệm'27'HCNS'38'HCNS'42'Hướng dẫn sử dụng'12'Kết Hợp Công Cụ Chuyển Đổi Bảng Công'41'Khác'67'Khác'24'Khách hàng - web'34'Kho'23'Kho đến'22'Kho đi'55'Kho Hàng'64'Khối Kinh doanh'13'Kiểm Thử Công Cụ'40'Kinh doanh'29'Kinh doanh HCM'28'Kinh doanh HN'58'KPI'30'Marketing'33'Phòng Tài chính Kế toán'32'Phòng Thông quan'26'Số hóa kho Nhật Bản'20'Số hóa Kho Việt Nam'21'Số hóa Phòng Data'17'Số hóa Phòng HCNS'18'Số hóa Phòng Kinh doanh'19'Số hòa Phòng Tài Chính'45'Sự cố hàng hóa'14'Sửa Lỗi Công Cụ'54'Sửa lỗi, nâng cấp'11'Tạo Công Cụ Xử Lý Dữ Liệu Thô'46'Trao đổi, thảo luận về hàng hóa'25'Vận hành'39'Vận hành'61'Xử lý định kỳ'
gettask_ids	string comma	false	Người đã nhận thực hiện
Là 1 chuỗi mã hoặc tên nhân sự được phân cách bởi dấu phẩy. Ví dụ: 'NV06,NV08,Nguyễn Văn C'
progress_type	string	false	Cách tính tiến độ công việc
Thuộc 1 trong các giá trị sau:(Sử dụng cột Giá trị, nếu cấu hình field_raws hãy sử dụng cột ID) IDGiá trịPERCENT'Theo % người dùng tự cập nhật'NUMBER'Theo tỷ lệ hoàn thành khối lượng công việc'TASKLIST'Theo tỷ lệ hoàn thành đầu việc'PROPORTION'Theo tỷ trọng công việc con'TIMEFINISH'Tự động theo thời gian hoàn thành công việc'
progress_unit	string	false	Đơn vị
Thuộc 1 trong các giá trị sau:(Sử dụng cột Giá trị, nếu cấu hình field_raws hãy sử dụng cột ID) IDGiá trị1'Đầu việc nhỏ'
department_created_id	string	false	Phòng ban người tạo
Phòng ban cha con được nối với nhau bởi dấu ||. Ví dụ 'Phòng kinh doanh || Nhóm 1' hoặc 'Cửa hàng A || Phòng điều hành || Ban giám đốc',....
repeat_template_id	integer	false	Công việc lặp
phase_id	integer	false	Phase
proportion	real	false	Tỷ trọng
created_by_id	string	false	Người tạo
Nhập mã nhân sự hoặc họ và tên của tài khoản người dùng đang hoạt động. Ví dụ: "NV001" hoặc "Nguyễn Văn A"...
diploma_id	integer	false	Công văn
workflow_type	string	false	Loại quy trình
Thuộc 1 trong các giá trị sau:(Sử dụng cột Giá trị, nếu cấu hình field_raws hãy sử dụng cột ID) IDGiá trịMANUAL'Tạo thủ công'Mặc địnhAUTO'Tạo tự động'
is_overdue_task	string	false	Là công việc quá hạn
Thuộc 1 trong các giá trị sau:(Sử dụng cột Giá trị, nếu cấu hình field_raws hãy sử dụng cột ID) IDGiá trị0'Không'1'Có'
date_pause_end	date	false	Tạm dừng đến ngày
Định dạng dd/mm/YYYY
rating_num_fail	integer	false	Số lần đánh giá không hoàn thành công việc
status_after_report*	string	true	Trạng thái sau báo cáo
Thuộc 1 trong các giá trị sau:(Sử dụng cột Giá trị, nếu cấu hình field_raws hãy sử dụng cột ID) IDGiá trịCOMPLETED'Hoàn thành'REVIEW'Đang đánh giá'Mặc định
tag_ids	string comma	false	Nhãn
Thuộc 1 trong các giá trị sau:(Sử dụng cột Giá trị, nếu cấu hình field_raws hãy sử dụng cột ID) IDGiá trịIDGiá trị012345678910111213141516171819202122232425262728293031323334353637383940
process_step	string	false	Bước quy trình
Thuộc 1 trong các giá trị sau:(Sử dụng cột Giá trị, nếu cấu hình field_raws hãy sử dụng cột ID) IDGiá trị
num_file	integer	false	Số lượng file đính kèm
start_plan_from	date	false	Từ bắt đầu dự kiến
Định dạng dd/mm/YYYY
start_plan_to	date	false	Đến bắt đầu dự kiến
Định dạng dd/mm/YYYY
end_plan_from	date	false	Từ kết thúc dự kiến
Định dạng dd/mm/YYYY
end_plan_to	date	false	Đến kết thúc dự kiến
Định dạng dd/mm/YYYY
start_from	date	false	Từ bắt đầu thực tế
Định dạng dd/mm/YYYY
start_to	date	false	Đến bắt đầu thực tế
Định dạng dd/mm/YYYY
end_from	date	false	Từ kết thúc thực tế
Định dạng dd/mm/YYYY
end_to	date	false	Đến kết thúc thực tế
Định dạng dd/mm/YYYY
date_created_from	date	false	Từ ngày tạo
Định dạng dd/mm/YYYY
date_created_to	date	false	Đến ngày tạo
Định dạng dd/mm/YYYY
date_updated_from	date	false	Từ ngày cập nhật
Định dạng dd/mm/YYYY
date_updated_to	date	false	Đến ngày cập nhật
Định dạng dd/mm/YYYY
date_pause_end_from	date	false	Từ tạm dừng đến ngày
Định dạng dd/mm/YYYY
date_pause_end_to	date	false	Đến tạm dừng đến ngày
Định dạng dd/mm/YYYY




