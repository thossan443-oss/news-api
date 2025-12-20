# 📰 News API

A lightweight **PHP-based News API** deployed on **Vercel**, designed to fetch and serve news in a clean JSON format. Currently includes a scraper/endpoint for **Jamuna TV** and supports incremental updates using a `last news id`.

---

## 🚀 Live Demo

* Jamuna TV news:

  ```
  https://news-api-kohl-delta.vercel.app/jamuna.tv
  ```

* Get only new news after a specific ID:

  ```
  https://news-api-kohl-delta.vercel.app/jamuna.tv?update=LAST_NEWS_ID
  ```

---

## 🧠 API Features

* ✅ PHP-based API on Vercel
* ✅ Clean REST-style endpoints
* ✅ Incremental updates via `update` query param
* ✅ Easy to extend with more news sources
* ✅ No database required (file / memory based logic)

---

## 🧪 Example Response (Simplified)

```json
{
  "success": true,
  "messsage": "Latest news successfully fetched.",
  "news": [
    {
      "id": 1256,
      "reporter": "Reporter",
      "time": "Time",
      "title": "News Title",
      "body": "News Body",
      "link": "https://example.news/....jpg"
    }
  ]
}
```

## ▲ One‑Click Deploy to Vercel

You can deploy this project to Vercel in minutes:

1. Fork or clone this repository
2. Go to **[https://vercel.com/new](https://vercel.com/new)**
3. Import the GitHub repo
4. Framework Preset: **Other**
5. Deploy 🎉

### Optional: Vercel Deploy Button

Add this button to deploy instantly:

[![Deploy with Vercel](https://vercel.com/button)](https://vercel.com/new/clone?repository-url=https://github.com/samiulalim1230/news-api)

---

## 🛠️ Local Development (Optional)

Vercel PHP runs serverless, but for local testing you can use:

```bash
php -S localhost:8000
```

Then open:

```
http://localhost:8000/api/jamuna.tv.php
```

---

## 🔮 Roadmap

* [ ] Add more Bangla news sources
* [ ] Caching support
* [ ] Rate limiting
* [ ] OpenAPI / Swagger docs

---

## 📄 License

MIT License

---

## 👤 Author

**Samiul Alim**
GitHub: [https://github.com/samiulalim1230](https://github.com/samiulalim1230)

---

⭐ If this project helps you, consider giving it a star!
