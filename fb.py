import requests

url = "https://www.facebook.com/messaging/send/"  # <-- đổi thành URL bạn cần gửi
headers = {
    "accept": "*/*",
    "accept-language": "vi,en-US;q=0.9,en;q=0.8",
    "content-type": "application/x-www-form-urlencoded",
    "cookie": "datr=tz2daMGKh03WseezECJjZJY3; sb=tz2daOE0yuuDO4PuUW9eITyo; dpr=1.25; ps_l=1; ps_n=1; c_user=100072984918904; xs=5%3A0IXbuaf2YdXAWQ%3A2%3A1755609267%3A-1%3A-1%3A%3AAcUVsRh9ZUrbj_ZQ0zFMQGklUbPP6yqbzy_1q3482iA; ar_debug=1; wd=150x778; fr=1KsYOZCgBODCtoU1s.AWcpl8_Ysw8QtkW0m1pf9Pf2kLsoy6wRj9C2cMG_MkQXUOt3I_Y.BosFnM..AAA.0.0.BosFtu.AWczmVF49DsUw8j5raCbZyO0a-E; presence=C%7B%22t3%22%3A%5B%5D%2C%22utc3%22%3A1756388219454%2C%22v%22%3A1%7Ddnt1",
    "origin": "https://www.facebook.com",
    "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.190 Safari/537.36"
}
data = {
    "message_text": "Xin chào",
    "thread_id": "https://www.facebook.com/phamlinh7114" 
}

res = requests.post(url, headers=headers, data=data)
print(res.text)
