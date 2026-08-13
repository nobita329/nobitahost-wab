require('dotenv').config();
const path = require('path');
const fs = require('fs');
const express = require('express');
const cookieParser = require('cookie-parser');
const { getSettings } = require('./settings');
const { loadUser } = require('./middleware/auth');
const authRoutes = require('./routes/auth.routes');
const webRoutes = require('./routes/web.routes');

const app = express();

app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, '..', 'views'));
app.set('trust proxy', true);

app.use(express.urlencoded({ extended: true }));
app.use(express.json());
app.use(cookieParser());

app.use('/uploads', express.static(path.join(__dirname, '..', 'public', 'uploads')));
app.use(express.static(path.join(__dirname, '..', 'public')));

app.use(loadUser);

app.use((req, res, next) => {
  const s = getSettings();
  if (s.maintenance === 'on' && !req.path.startsWith('/login')) {
    const isAdmin = req.user && req.user.role === 'admin';
    if (!isAdmin) {
      return res.status(503).render('pages/errors/maintenance', { title: 'Maintenance', active: '', bodyClass: 'auth-page', bg: { url: '', type: 'image' } });
    }
  }
  next();
});

app.get('/favicon.ico', (req, res) => {
  const s = getSettings();
  const fav = s.favicon_url || '/img/favicon.svg';
  const p = path.join(__dirname, '..', 'public', fav.replace(/^\//, ''));
  if (fs.existsSync(p)) return res.sendFile(p);
  res.sendFile(path.join(__dirname, '..', 'public', 'img', 'favicon.svg'));
});

app.use('/', authRoutes);
app.use('/', webRoutes);

app.use((req, res) => {
  res.status(404).render('pages/errors/404', { title: '404', active: '', bodyClass: 'auth-page', bg: { url: '', type: 'image' } });
});

app.use((err, req, res, next) => {
  console.error(err);
  res.status(500).render('pages/errors/500', { title: '500', active: '', bodyClass: 'auth-page', bg: { url: '', type: 'image' } });
});

const PORT = process.env.PORT || 3001;
app.listen(PORT, () => {
  console.log(`[NobitaHost] Web Panel  -> http://localhost:${PORT}`);
});
