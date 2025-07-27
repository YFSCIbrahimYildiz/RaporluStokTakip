document.addEventListener("DOMContentLoaded", () => {
    kullaniciGetir();
    urunleriYukle();
  });
  
  let seciliUrun = "";
  let sicilNo = "";
  
  function kullaniciGetir() {
    fetch("api/kullanici_bilgisi.php")
      .then(res => res.json())
      .then(veri => {
        if (!veri.basarili) return alert("Kullanıcı bilgisi alınamadı.");
        document.getElementById("kullaniciBilgisi").innerText = veri.adi + " " + veri.soyadi;
        document.getElementById("sicilGoster").innerText = veri.sicil_no;
        sicilNo = veri.sicil_no;
      })
      .catch(err => console.error("Kullanıcı getirme hatası:", err));
  }
  
  function urunleriYukle() {
    fetch("api/urun_listele.php")
      .then(res => res.json())
      .then(veri => {
        const liste = document.getElementById("urunListesi");
        veri.forEach(u => {
          const tr = document.createElement("tr");
          tr.innerHTML = `<td>${u.urun_kodu}</td><td>${u.urun_adi}</td>`;
          tr.onclick = () => {
            seciliUrun = u.urun_kodu;
            document.getElementById("seciliMalzeme").innerText = u.urun_kodu + " - " + u.urun_adi;
          };
          liste.appendChild(tr);
        });
      })
      .catch(err => console.error("Ürün yükleme hatası:", err));
  }
  
  function filtrele() {
    const arama = document.getElementById("urunAra").value.toLowerCase();
    const satirlar = document.querySelectorAll("#urunListesi tr");
    satirlar.forEach(tr => {
      const metin = tr.innerText.toLowerCase();
      tr.style.display = metin.includes(arama) ? "" : "none";
    });
  }
  
  function talepGonder() {
    const adet = document.getElementById("adet").value;
    const sebep = document.getElementById("talepSebebi").value;
    const aciliyet = document.getElementById("aciliyet").value;
  
    if (!seciliUrun || !adet || !sebep) {
      alert("Lütfen tüm alanları doldurun ve bir ürün seçin.");
      return;
    }
  
    fetch("api/talep_kaydet.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({
        urun_kodu: seciliUrun,
        adet,
        sebep,
        aciliyet,
        sicil_no: sicilNo
      })
    })
      .then(res => res.json())
      .then(cevap => {
        alert(cevap.mesaj);
        if (cevap.basarili) location.reload();
      })
      .catch(err => console.error("Talep gönderme hatası:", err));
  }