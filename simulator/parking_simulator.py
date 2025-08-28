import tkinter as tk
from tkinter import ttk, messagebox, simpledialog
import requests
import qrcode
from PIL import Image, ImageTk
import threading
import time
import json
from http.server import HTTPServer, BaseHTTPRequestHandler
import webbrowser

class ParkingSimulator:
    def __init__(self):
        self.root = tk.Tk()
        self.root.title("🚗 Smart Parking Simulator - XParking")
        self.root.geometry("1200x800")
        self.root.configure(bg='#2c3e50')
        
        # API Configuration
        self.api_base = "https://xparking.x10.mx/api"
        self.site_url = "https://xparking.x10.mx"
        
        # RFID Cards Data
        self.rfid_cards = {
            'A01': 'CD290C73',
            'A02': 'AB123456', 
            'A03': 'EF789012',
            'A04': 'GH345678',
            'A05': 'IJ901234'
        }
        
        # Slot states
        self.slots = {
            'A01': {'status': 'empty', 'license_plate': '', 'user': '', 'entry_time': ''},
            'A02': {'status': 'empty', 'license_plate': '', 'user': '', 'entry_time': ''},
            'A03': {'status': 'empty', 'license_plate': '', 'user': '', 'entry_time': ''},
            'A04': {'status': 'empty', 'license_plate': '', 'user': '', 'entry_time': ''},
            'A05': {'status': 'empty', 'license_plate': '', 'user': '', 'entry_time': ''}
        }
        
        self.setup_ui()
        self.start_webhook_server()
        self.refresh_slots()
        
    def setup_ui(self):
        # Header
        header = tk.Frame(self.root, bg='#34495e', height=80)
        header.pack(fill='x', padx=10, pady=5)
        
        title = tk.Label(header, text="🚗 SMART PARKING SIMULATOR", 
                        font=('Arial', 20, 'bold'), fg='white', bg='#34495e')
        title.pack(pady=20)
        
        # Main container
        main_container = tk.Frame(self.root, bg='#2c3e50')
        main_container.pack(fill='both', expand=True, padx=10)
        
        # Left panel - Parking slots
        left_panel = tk.Frame(main_container, bg='#ecf0f1', width=700)
        left_panel.pack(side='left', fill='both', expand=True, padx=(0, 10))
        
        slots_title = tk.Label(left_panel, text="🅿️ BÃI ĐỖ XE (5 SLOTS)", 
                              font=('Arial', 16, 'bold'), bg='#ecf0f1')
        slots_title.pack(pady=10)
        
        # Slots grid
        self.slots_frame = tk.Frame(left_panel, bg='#ecf0f1')
        self.slots_frame.pack(fill='both', expand=True, padx=20, pady=20)
        
        self.slot_widgets = {}
        self.create_slot_widgets()
        
        # Right panel - Controls
        right_panel = tk.Frame(main_container, bg='#3498db', width=450)
        right_panel.pack(side='right', fill='y')
        
        # Control buttons
        controls_title = tk.Label(right_panel, text="🎮 ĐIỀU KHIỂN", 
                                 font=('Arial', 16, 'bold'), fg='white', bg='#3498db')
        controls_title.pack(pady=15)
        
        # Refresh button
        refresh_btn = tk.Button(right_panel, text="🔄 Làm mới trạng thái", 
                               command=self.refresh_slots, font=('Arial', 12, 'bold'),
                               bg='#27ae60', fg='white', height=2, width=25)
        refresh_btn.pack(pady=10)
        
        # Open website button
        website_btn = tk.Button(right_panel, text="🌐 Mở Website", 
                               command=self.open_website, font=('Arial', 12, 'bold'),
                               bg='#e74c3c', fg='white', height=2, width=25)
        website_btn.pack(pady=10)
        
        # RFID Cards section
        rfid_title = tk.Label(right_panel, text="🏷️ THẺ RFID", 
                             font=('Arial', 14, 'bold'), fg='white', bg='#3498db')
        rfid_title.pack(pady=(30, 10))
        
        self.create_rfid_buttons(right_panel)
        
        # Status info
        status_title = tk.Label(right_panel, text="📊 TRẠNG THÁI HỆ THỐNG", 
                               font=('Arial', 14, 'bold'), fg='white', bg='#3498db')
        status_title.pack(pady=(30, 10))
        
        self.status_text = tk.Text(right_panel, height=10, width=35, 
                                  font=('Courier', 9), bg='#ecf0f1')
        self.status_text.pack(padx=10, pady=10)
        
    def create_slot_widgets(self):
        for i, (slot_id, rfid_id) in enumerate(self.rfid_cards.items()):
            row = i // 3
            col = i % 3
            
            slot_frame = tk.Frame(self.slots_frame, bg='#95a5a6', width=200, height=150, 
                                 relief='raised', bd=2)
            slot_frame.grid(row=row, column=col, padx=10, pady=10, sticky='nsew')
            slot_frame.grid_propagate(False)
            
            # Slot label
            slot_label = tk.Label(slot_frame, text=f"SLOT {slot_id}", 
                                 font=('Arial', 12, 'bold'), bg='#95a5a6')
            slot_label.pack(pady=5)
            
            # Status indicator
            status_label = tk.Label(slot_frame, text="TRỐNG", 
                                   font=('Arial', 10, 'bold'), 
                                   bg='#2ecc71', fg='white', width=12)
            status_label.pack(pady=2)
            
            # License plate display
            plate_label = tk.Label(slot_frame, text="", 
                                  font=('Arial', 9, 'bold'), bg='#95a5a6')
            plate_label.pack(pady=2)
            
            # Action buttons
            btn_frame = tk.Frame(slot_frame, bg='#95a5a6')
            btn_frame.pack(pady=5)
            
            park_btn = tk.Button(btn_frame, text="🚗 VÀO", 
                               command=lambda s=slot_id: self.vehicle_enter(s),
                               bg='#3498db', fg='white', width=6)
            park_btn.pack(side='left', padx=2)
            
            exit_btn = tk.Button(btn_frame, text="🚪 RA", 
                               command=lambda s=slot_id: self.vehicle_exit(s),
                               bg='#e74c3c', fg='white', width=6)
            exit_btn.pack(side='left', padx=2)
            
            self.slot_widgets[slot_id] = {
                'frame': slot_frame,
                'status': status_label,
                'plate': plate_label,
                'park_btn': park_btn,
                'exit_btn': exit_btn
            }
    
    def create_rfid_buttons(self, parent):
        for slot_id, rfid_id in self.rfid_cards.items():
            btn_frame = tk.Frame(parent, bg='#3498db')
            btn_frame.pack(pady=5, fill='x', padx=10)
            
            rfid_btn = tk.Button(btn_frame, text=f"📱 {slot_id}: {rfid_id}", 
                               command=lambda r=rfid_id: self.show_qr_code(r),
                               font=('Arial', 10), bg='#f39c12', fg='white', 
                               width=30, height=1)
            rfid_btn.pack()
    
    def show_qr_code(self, rfid_id):
        qr_url = f"{self.site_url}/pay.php?uid={rfid_id}"
        
        # Generate QR code
        qr = qrcode.QRCode(version=1, box_size=10, border=5)
        qr.add_data(qr_url)
        qr.make(fit=True)
        
        qr_img = qr.make_image(fill_color="black", back_color="white")
        
        # Show QR in popup
        qr_window = tk.Toplevel(self.root)
        qr_window.title(f"QR Code - RFID: {rfid_id}")
        qr_window.geometry("400x450")
        qr_window.configure(bg='white')
        
        # Convert PIL image to PhotoImage
        qr_photo = ImageTk.PhotoImage(qr_img)
        
        qr_label = tk.Label(qr_window, image=qr_photo, bg='white')
        qr_label.image = qr_photo  # Keep reference
        qr_label.pack(pady=20)
        
        info_label = tk.Label(qr_window, text=f"RFID: {rfid_id}\nURL: {qr_url}", 
                             font=('Arial', 10), bg='white', wraplength=350)
        info_label.pack(pady=10)
        
        copy_btn = tk.Button(qr_window, text="📋 Copy URL", 
                           command=lambda: self.copy_to_clipboard(qr_url),
                           bg='#3498db', fg='white')
        copy_btn.pack(pady=10)
    
    def copy_to_clipboard(self, text):
        self.root.clipboard_clear()
        self.root.clipboard_append(text)
        messagebox.showinfo("Copied", "URL đã được copy!")
    
    def vehicle_enter(self, slot_id):
        if self.slots[slot_id]['status'] != 'empty':
            messagebox.showwarning("Lỗi", f"Slot {slot_id} đã có xe!")
            return
            
        license_plate = simpledialog.askstring("Biển số xe", 
                                              f"Nhập biển số xe vào slot {slot_id}:")
        if not license_plate:
            return
            
        # Call API to update slot
        try:
            response = requests.post(f"{self.api_base}/slots.php", 
                json={
                    'action': 'enter',
                    'slot_id': slot_id,
                    'license_plate': license_plate,
                    'rfid_id': self.rfid_cards[slot_id]
                }, timeout=10)
            
            if response.status_code == 200:
                data = response.json()
                if data.get('success'):
                    self.update_slot_display(slot_id, 'occupied', license_plate)
                    self.log_status(f"✅ Xe {license_plate} vào slot {slot_id}")
                else:
                    messagebox.showerror("Lỗi", data.get('message', 'Unknown error'))
            else:
                messagebox.showerror("Lỗi", f"API Error: {response.status_code}")
                
        except requests.exceptions.RequestException as e:
            messagebox.showerror("Lỗi kết nối", f"Không thể kết nối API: {str(e)}")
    
    def vehicle_exit(self, slot_id):
        if self.slots[slot_id]['status'] != 'occupied':
            messagebox.showwarning("Lỗi", f"Slot {slot_id} không có xe!")
            return
        
        # Call API to process exit
        try:
            response = requests.post(f"{self.api_base}/slots.php",
                json={
                    'action': 'exit',
                    'slot_id': slot_id,
                    'rfid_id': self.rfid_cards[slot_id]
                }, timeout=10)
            
            if response.status_code == 200:
                data = response.json()
                if data.get('success'):
                    self.update_slot_display(slot_id, 'empty', '')
                    
                    # Show payment info if needed
                    if data.get('requires_payment'):
                        payment_info = f"""
                        💰 THANH TOÁN CẦN THIẾT
                        Slot: {slot_id}
                        Biển số: {self.slots[slot_id]['license_plate']}
                        Số tiền: {data.get('amount', 0):,}đ
                        Quét QR RFID để thanh toán!
                        """
                        messagebox.showinfo("Cần thanh toán", payment_info)
                    
                    self.log_status(f"🚪 Xe ra khỏi slot {slot_id}")
                else:
                    messagebox.showerror("Lỗi", data.get('message', 'Unknown error'))
                    
        except requests.exceptions.RequestException as e:
            messagebox.showerror("Lỗi kết nối", f"Không thể kết nối API: {str(e)}")
    
    def update_slot_display(self, slot_id, status, license_plate='', user=''):
        self.slots[slot_id]['status'] = status
        self.slots[slot_id]['license_plate'] = license_plate
        self.slots[slot_id]['user'] = user
        
        widget = self.slot_widgets[slot_id]
        
        if status == 'empty':
            widget['status'].config(text='TRỐNG', bg='#2ecc71')
            widget['frame'].config(bg='#95a5a6')
            widget['plate'].config(text='')
        elif status == 'occupied':
            widget['status'].config(text='CÓ XE', bg='#e74c3c')
            widget['frame'].config(bg='#e67e22')
            widget['plate'].config(text=license_plate)
        elif status == 'reserved':
            widget['status'].config(text='ĐÃ ĐẶT', bg='#f39c12')
            widget['frame'].config(bg='#f1c40f')
            widget['plate'].config(text=license_plate)
    
    def refresh_slots(self):
        try:
            response = requests.get(f"{self.api_base}/slots.php", timeout=10)
            if response.status_code == 200:
                data = response.json()
                if data.get('success'):
                    for slot in data.get('slots', []):
                        slot_id = slot['slot_number']
                        if slot_id in self.slots:
                            self.update_slot_display(
                                slot_id, 
                                slot['status'], 
                                slot.get('license_plate', ''),
                                slot.get('user', '')
                            )
                    
                    self.log_status("🔄 Đã làm mới trạng thái slots")
                    
        except requests.exceptions.RequestException as e:
            self.log_status(f"❌ Lỗi làm mới: {str(e)}")
    
    def log_status(self, message):
        timestamp = time.strftime("%H:%M:%S")
        log_message = f"[{timestamp}] {message}\n"
        
        self.status_text.insert(tk.END, log_message)
        self.status_text.see(tk.END)
        
        # Keep only last 50 lines
        lines = self.status_text.get("1.0", tk.END).split("\n")
        if len(lines) > 50:
            self.status_text.delete("1.0", "2.0")
    
    def open_website(self):
        webbrowser.open(self.site_url)
    
    def start_webhook_server(self):
        def run_server():
            class WebhookHandler(BaseHTTPRequestHandler):
                def do_POST(self):
                    content_length = int(self.headers['Content-Length'])
                    post_data = self.rfile.read(content_length)
                    
                    try:
                        data = json.loads(post_data.decode('utf-8'))
                        self.server.simulator.handle_webhook(data)
                        
                        self.send_response(200)
                        self.send_header('Content-type', 'application/json')
                        self.end_headers()
                        self.wfile.write(b'{"status": "ok"}')
                    except Exception as e:
                        self.send_response(400)
                        self.end_headers()
                        self.wfile.write(f'{{"error": "{str(e)}"}}'.encode())
                
                def log_message(self, format, *args):
                    pass  # Suppress default logging
            
            server = HTTPServer(('localhost', 8080), WebhookHandler)
            server.simulator = self
            server.serve_forever()
        
        webhook_thread = threading.Thread(target=run_server, daemon=True)
        webhook_thread.start()
        self.log_status("🌐 Webhook server started on port 8080")
    
    def handle_webhook(self, data):
        action = data.get('action')
        webhook_data = data.get('data', {})
        
        if action == 'payment_completed':
            slot_id = webhook_data.get('slot_id')
            amount = webhook_data.get('amount')
            self.log_status(f"💰 Thanh toán thành công slot {slot_id}: {amount:,}đ")
        
        elif action == 'booking_created':
            slot_id = webhook_data.get('slot_id')
            license_plate = webhook_data.get('license_plate')
            self.update_slot_display(slot_id, 'reserved', license_plate)
            self.log_status(f"📅 Booking mới slot {slot_id}: {license_plate}")
        
        elif action == 'slot_updated':
            slot_id = webhook_data.get('slot_id')
            status = webhook_data.get('status')
            license_plate = webhook_data.get('license_plate', '')
            self.update_slot_display(slot_id, status, license_plate)
            self.log_status(f"🔄 Slot {slot_id} cập nhật: {status}")
    
    def run(self):
        # Auto refresh every 30 seconds
        def auto_refresh():
            self.refresh_slots()
            self.root.after(30000, auto_refresh)
        
        auto_refresh()
        self.root.mainloop()

if __name__ == "__main__":
    app = ParkingSimulator()
    app.run()