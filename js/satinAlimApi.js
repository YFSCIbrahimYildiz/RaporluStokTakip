document.addEventListener("DOMContentLoaded", () => {
  urunleriGetir();
  firmalariGetir();
  alimGecmisiGetir();
});

let secilenId = null;
let faturaYolu = "";

function urunleriGetir() {
  fetch("api/urunler.php")
    .then(res => res.json())
    .then(data => {
      if (!Array.isArray(data)) {
        alert("Ürün listesi alınamadı.");
        return;
      }

      const select = document.getElementById("malzemeKodu");
      select.innerHTML = '<option value="">-- Ürün Seçin --</option>';
      data.forEach(u => {
        const opt = document.createElement("option");
        opt.value = u.id;
        opt.textContent = `${u.urun_adi} (${u.urun_kodu})`;
        opt.setAttribute("data-kdv", u.kdv);
        select.appendChild(opt);
      });
    })
    .catch(err => {
      console.error("API hatası:", err);
      alert("Sunucuya bağlanılamadı (urunler).");
    });
}

function firmalariGetir() {
  fetch("api/firmalar.php")
    .then(res => res.json())
    .then(data => {
      if (!Array.isArray(data)) {
        alert("Firma listesi alınamadı.");
        return;
      }

      const select = document.getElementById("firmaKodu");
      select.innerHTML = '<option value="">-- Firma Seçin --</option>';
      data.forEach(f => {
        const opt = document.createElement("option");
        opt.value = f.firma_kodu;
        opt.textContent = f.firma_adi;
        select.appendChild(opt);
      });
    })
    .catch(err => {
      console.error("API hatası:", err);
      alert("Sunucuya bağlanılamadı (firmalar).");
    });
}

function urunSecildi() {
  const select = document.getElementById("malzemeKodu");
  const selectedOption = select.options[select.selectedIndex];
  document.getElementById("kdv").value = selectedOption.getAttribute("data-kdv") || "";
}

function stokEkle() {
  const formData = new FormData();
  formData.append("urun_kodu", document.getElementById("malzemeKodu").value);
  formData.append("firma_kodu", document.getElementById("firmaKodu").value);
  formData.append("adet", document.getElementById("adet").value);
  formData.append("adet_fiyat", document.getElementById("adetFiyat").value);
  formData.append("kdv", document.getElementById("kdv").value);
  formData.append("alim_tarihi", document.getElementById("sevkTarihi").value);

  console.log("stokEkle veri:", Object.fromEntries(formData));

  fetch("api/stok_ekle.php", {
    method: "POST",
    body: formData
  })
    .then(res => res.json())
    .then(data => {
      alert(data.mesaj);
      if (data.durum === "ok") {
        secilenId = data.id;
        faturaYolu = null;
        document.getElementById("faturaYukleBtn").disabled = false;
        document.getElementById("faturaGosterBtn").disabled = true;
        alimGecmisiGetir();
      }
    })
    .catch(err => {
      console.error("Stok ekleme hatası:", err);
      alert("Stok ekleme işlemi başarısız oldu.");
    });
}

function faturaEkle() {
  if (!secilenId) return alert("Lütfen bir kayıt seçiniz.");

  const dosya = document.createElement("input");
  dosya.type = "file";
  dosya.accept = ".pdf,.jpg,.png";

  dosya.onchange = function () {
    const formData = new FormData();
    formData.append("id", secilenId);
    formData.append("fatura", dosya.files[0]);

    fetch("api/fatura_yukle.php", {
      method: "POST",
      body: formData
    })
      .then(res => res.json())
      .then(data => {
        alert(data.mesaj);
        if (data.durum === "ok") {
          faturaYolu = "faturalar/" + dosya.files[0].name;
          document.getElementById("faturaGosterBtn").disabled = false;
          alimGecmisiGetir();
        }
      })
      .catch(err => {
        console.error("Fatura yükleme hatası:", err);
        alert("Fatura yükleme başarısız oldu.");
      });
  };

  dosya.click();
}

function faturaGoster() {
  if (!faturaYolu) return alert("Fatura bulunamadı.");
  window.location.href = "faturalar/" + faturaYolu;
}

function alimGecmisiGetir() {
  fetch("api/alim_gecmisi_getir.php")
    .then(res => res.json())
    .then(data => {
      const tbody = document.getElementById("gecmisListe");
      tbody.innerHTML = "";
      data.forEach(item => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
          <td>${item.urun_kodu}</td>
          <td>${item.urun_adi}</td>
          <td>${item.firma_kodu}</td>
          <td>${item.firma_adi}</td>
          <td>${item.alinan_adet}</td>
          <td>${item.adet_fiyat}</td>
          <td>-</td>
          <td>${item.alim_tarihi}</td>
          <td>${item.toplam}</td>
          <td>
            ${item.fatura_dosya_yolu
              ? `<a href="faturalar/${item.fatura_dosya_yolu}" target="_blank">Yeni Sekme</a>`
              : 'Yok'}
          </td>`;
        tr.onclick = () => {
          secilenId = item.id;
          faturaYolu = item.fatura_dosya_yolu;
          document.getElementById("faturaGosterBtn").disabled = !faturaYolu;
          document.getElementById("faturaYukleBtn").disabled = false;
        };
        tbody.appendChild(tr);
      });
    })
    .catch(err => {
      console.error("Alım geçmişi getirilemedi:", err);
    });
}