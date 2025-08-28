import requests
import time
import json
import base64
import os
import qrcode
from PIL import Image
from io import BytesIO

# --- Cấu hình API và đường dẫn ---
SITE_URL = 'https://xparking.x10.mx'
CHECKIN_API = f'{SITE_URL}/api/checkin.php'
CHECKOUT_API = f'{SITE_URL}/api/checkout.php'
CHECK_PAYMENT_API = f'{SITE_URL}/api/check_payment.php'

# --- Hàm mô phỏng ---
def get_user_input(prompt):
    """Lấy input từ người dùng, cho phép thoát bằng 'q'."""
    user_input = input(f"> {prompt}: ").strip()
    if user_input.lower() == 'q':
        return None
    return user_input

def show_qr_code(qr_data):
    """Hiển thị QR code bằng thư viện qrcode và PIL."""
    try:
        qr = qrcode.QRCode(
            version=1,
            error_correction=qrcode.constants.ERROR_CORRECT_L,
            box_size=10,
            border=4,
        )
        qr.add_data(qr_data)
        qr.make(fit=True)
        img = qr.make_image(fill_color="black", back_color="white")
        img.show()
    except Exception as e:
        print(f"Không thể hiển thị QR code. Vui lòng quét URL sau: {qr_data}")
        print(f"Lỗi: {e}")

def main():
    """Chương trình chính để mô phỏng quy trình."""
    print("--- Mô phỏng hệ thống đỗ xe XParking ---")
    print("Nhập 'q' bất cứ lúc nào để thoát.")

    while True:
        print("\nChọn chức năng:")
        print("1. Xe vào (Check-in)")
        print("2. Xe ra (Check-out)")
        print("3. Thoát")

        choice = get_user_input("Lựa chọn của bạn")
        if choice is None or choice == '3':
            break

        if choice == '1':
            print("\n--- Xe vào bãi ---")
            license_plate = get_user_input("Nhập biển số xe (VD: 51F-123.45)")
            if not license_plate:
                continue

            # Chuẩn bị dữ liệu và gửi yêu cầu
            payload = {'license_plate': license_plate, 'image_path': 'entry_image.jpg'}
            try:
                response = requests.post(CHECKIN_API, data=payload)
                result = response.json()
                print(f"\nKết quả: {json.dumps(result, indent=4, ensure_ascii=False)}")

                if result.get('success'):
                    print("\n✅ Xe đã vào bãi thành công!")
                    print(f"Biển số: {result['license_plate']}")
                    print(f"Slot đỗ: {result['slot_id']}")
                    print(f"RFID được gán: {result['rfid']}")
                    
                    if result.get('has_booking'):
                        print("📝 Thông báo: Xe này đã có đặt chỗ trước, không cần thanh toán phí đỗ.")
                    
                else:
                    print(f"❌ Lỗi: {result.get('error', 'Không xác định')}")

            except requests.exceptions.RequestException as e:
                print(f"❌ Lỗi kết nối: {e}")

        elif choice == '2':
            print("\n--- Xe ra bãi ---")
            rfid_tag = get_user_input("Nhập mã RFID (VD: CD290C73)")
            if not rfid_tag:
                continue
            
            # Gửi yêu cầu check-out
            payload = {'rfid': rfid_tag, 'image_path': 'exit_image.jpg'}
            try:
                response = requests.post(CHECKOUT_API, data=payload)
                result = response.json()
                print(f"\nKết quả: {json.dumps(result, indent=4, ensure_ascii=False)}")

                if result.get('success'):
                    if result.get('payment_required'):
                        print("💳 Yêu cầu thanh toán:")
                        print(f"Tổng phí: {result['fee']} VNĐ")
                        print(f"Mã thanh toán: {result['payment_ref']}")
                        print(f"QR Code URL: {result['qr_code']}")
                        
                        # Hiển thị QR code
                        show_qr_code(result['qr_code'])

                        # Vòng lặp kiểm tra trạng thái thanh toán
                        payment_status = "pending"
                        while payment_status == "pending":
                            print("\nĐang chờ thanh toán...")
                            time.sleep(3)
                            
                            check_response = requests.get(f"{CHECK_PAYMENT_API}?ref={result['payment_ref']}")
                            check_result = check_response.json()
                            payment_status = check_result.get('status')
                            
                            if payment_status == "completed":
                                print("\n✅ Thanh toán thành công! Cổng đã mở.")
                                break
                            elif payment_status == "failed" or payment_status == "expired":
                                print(f"❌ Thanh toán thất bại hoặc đã hết hạn: {payment_status}. Vui lòng thử lại.")
                                break
                    else:
                        print("✅ Xe ra thành công! Không có phí phát sinh.")
                        
                else:
                    print(f"❌ Lỗi: {result.get('error', 'Không xác định')}")
                    
            except requests.exceptions.RequestException as e:
                print(f"❌ Lỗi kết nối: {e}")
    
    print("\nKết thúc chương trình mô phỏng.")

if __name__ == "__main__":
    main()