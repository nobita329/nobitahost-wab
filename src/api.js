require('dotenv').config();
const express = require('express');
const cookieParser = require('cookie-parser');
const apiRoutes = require('./routes/api.routes');

const app = express();
app.set('trust proxy', true);
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(cookieParser());

app.use('/api', apiRoutes);

app.get('/api/status', (req, res) => {
  res.json({ success: true, name: 'NobitaHost API', version: '1.0.0', time: new Date().toISOString() });
});

app.use('/api', (req, res) => {
  res.status(404).json({ success: false, error: 'API route not found' });
});

const PORT = process.env.API_PORT || 3002;
app.listen(PORT, () => {
  console.log(`[NobitaHost] API Panel   -> http://localhost:${PORT}`);
});
