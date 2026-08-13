(function () {
  'use strict';

  var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  var DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  var DOWS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
  var DEFAULT_LOC = { city: 'New Delhi', state: 'Delhi', country: 'India', lat: 28.6139, lon: 77.2090 };

  function pad(n) { return n < 10 ? '0' + n : '' + n; }
  function byId(id) { return document.getElementById(id); }
  function setText(id, text) { var el = byId(id); if (el) el.textContent = text; }

  /* ---------- Live Time (HH : MM : SS) ---------- */
  function fmtShort(d) { return DAYS[d.getDay()].slice(0, 3) + ', ' + d.getDate() + ' ' + MONTHS[d.getMonth()].slice(0, 3); }
  function fmtLong(d) { return DAYS[d.getDay()] + ', ' + d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear(); }

  function tickClock() {
    var now = new Date();
    var h = now.getHours();
    var ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12;
    if (h === 0) h = 12;
    setText('clockHH', pad(h));
    setText('clockMM', pad(now.getMinutes()));
    setText('clockSS', pad(now.getSeconds()));
    setText('clockAMPM', ampm);
    setText('clockDay', DAYS[now.getDay()]);
  }

  /* ---------- Calendar ---------- */
  var view = new Date();
  view.setDate(1);

  function renderCalendar() {
    var now = new Date();
    var y = view.getFullYear(), m = view.getMonth();
    setText('calDate', fmtLong(view));
    setText('calMonth', MONTHS[m] + ' ' + y);

    var html = DOWS.map(function (d) { return '<span class="cal-dow">' + d + '</span>'; }).join('');
    var startPad = view.getDay();
    var daysIn = new Date(y, m + 1, 0).getDate();
    for (var i = 0; i < startPad; i++) html += '<span class="cal-cell empty"></span>';
    for (var d = 1; d <= daysIn; d++) {
      var cls = 'cal-cell';
      var dow = new Date(y, m, d).getDay();
      if (dow === 0 || dow === 6) cls += ' weekend';
      if (d === now.getDate() && m === now.getMonth() && y === now.getFullYear()) cls += ' today';
      html += '<span class="' + cls + '">' + d + '</span>';
    }
    byId('calGrid').innerHTML = html;
  }

  var calPrev = byId('calPrev');
  var calNext = byId('calNext');
  if (calPrev) calPrev.addEventListener('click', function () { view.setMonth(view.getMonth() - 1); renderCalendar(); });
  if (calNext) calNext.addEventListener('click', function () { view.setMonth(view.getMonth() + 1); renderCalendar(); });

  /* ---------- Weather + Location (auto detect · India) ---------- */
  function weatherInfo(code) {
    if (code === 0) return ['☀️', 'Clear sky'];
    if (code === 1) return ['🌤', 'Mostly clear'];
    if (code === 2) return ['⛅', 'Partly cloudy'];
    if (code === 3) return ['☁️', 'Overcast'];
    if (code === 45 || code === 48) return ['🌫', 'Foggy'];
    if (code >= 51 && code <= 57) return ['🌦', 'Drizzle'];
    if (code >= 61 && code <= 67) return ['🌧', 'Rain'];
    if (code >= 71 && code <= 77) return ['🌨', 'Snow'];
    if (code >= 80 && code <= 82) return ['🌦', 'Rain showers'];
    if (code >= 95) return ['⛈', 'Thunderstorm'];
    return ['🌤', '—'];
  }

  function loadWeather(loc) {
    setText('weatherLoc', loc.city + ', ' + loc.state + ' · ' + loc.country);

    var url = 'https://api.open-meteo.com/v1/forecast?latitude=' + loc.lat +
      '&longitude=' + loc.lon +
      '&current=temperature_2m,relative_humidity_2m,wind_speed_10m,weather_code';
    fetch(url)
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(function (data) {
        var c = data.current;
        var info = weatherInfo(c.weather_code);
        setText('weatherEmoji', info[0]);
        setText('weatherCond', info[1]);
        setText('weatherTemp', Math.round(c.temperature_2m) + '°C');
        setText('weatherMini', '💧 ' + Math.round(c.relative_humidity_2m) + '% · 💨 ' + Math.round(c.wind_speed_10m) + ' km/h');
      })
      .catch(function () {
        setText('weatherEmoji', '⚠️');
        setText('weatherCond', 'Weather unavailable');
        setText('weatherTemp', '--°C');
        setText('weatherMini', '💧 --% · 💨 -- km/h');
      });
  }

  function autoDetect() {
    fetch('https://ipwho.is/')
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(function (geo) {
        if (geo && geo.success !== false && geo.latitude && geo.longitude) {
          return {
            city: geo.city || DEFAULT_LOC.city,
            state: geo.region || DEFAULT_LOC.state,
            country: geo.country || 'India',
            lat: geo.latitude,
            lon: geo.longitude
          };
        }
        throw new Error('No location');
      })
      .then(loadWeather)
      .catch(function () { loadWeather(DEFAULT_LOC); });
  }

  document.addEventListener('DOMContentLoaded', function () {
    tickClock();
    setInterval(tickClock, 1000);
    renderCalendar();
    autoDetect();
  });
})();
