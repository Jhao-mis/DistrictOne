<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>About - Development Team</title>

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/districtone.png" />

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    body {
      background: linear-gradient(135deg, #f3f6f9 0%, #e2eafc 100%);
      font-family: "Segoe UI", sans-serif;
      margin: 0;
    }

    .profile-container {
      max-width: 1000px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 40px;
      flex-wrap: wrap;
      text-align: left;
    }

    .profile-image {
      flex: 0 1 300px;
      text-align: center;
    }

    .profile-image img {
      width: 100%;
      max-width: 300px;
      border-radius: 10px;
      opacity: 0;
      transform: translateX(50px);
      animation: slideIn 1s ease forwards;
    }

    .profile-text {
  flex: 1 1 45%;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 20px;
  animation: fadeInUp 1s ease forwards;
  animation-delay: 0.2s;
  opacity: 0;
  transform: translateY(20px);
}

.profile-text .title {
  font-size: 2rem;
  font-weight: 700;
  margin-bottom: 5px;
  color: #2c3e50;
}

.profile-text .subtitle {
  font-size: 1.1rem;
  font-weight: 500;
  color: #6c757d;
  margin-bottom: 20px;
}

.profile-text .description {
  font-size: 1rem;
  color: #444;
  line-height: 1.6;
}

    @keyframes fadeInUp {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes slideIn {
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    .profile-slide {
  display: none;
  min-height: 450px; /* Adjust based on your tallest slide */
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  padding: 20px;
  box-sizing: border-box;
}

.profile-slide.active {
  display: flex;
}

/* Optional: Force image and text containers to have the same height */
.profile-image,
.profile-text {
  flex: 1 1 45%;
  min-height: 400px;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: left;
}

/* Make sure images don’t resize weirdly */
.profile-image img {
  max-height: 400px;
  object-fit: cover;
  width: 100%;
  border-radius: 10px;
}

    .title {
      font-size: 2.2rem;
      font-weight: 600;
    }

    .subtitle {
      font-size: 1.1rem;
      color: #6c757d;
      margin-bottom: 1.5rem;
    }

    .description {
      font-size: 1rem;
      color: #333;
    }

    @media (max-width: 768px) {
      .profile-container {
        flex-direction: column;
        text-align: center;
      }

      .profile-text {
        text-align: center;
      }
    }

    .arrow-btn {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      background-color: transparent;
      border: none;
      padding: 10px 15px;
      font-size: 2rem;
      cursor: pointer;
      z-index: 10;
      color: #333;
      transition: color 0.3s ease;
    }

    .arrow-btn:hover {
      color: #000;
    }

    #prevBtn {
      left: 10px;
    }

    #nextBtn {
      right: 10px;
    }
    .profile-wrapper {
  position: relative;
  max-width: 1000px;
  margin: 0 auto;
}

.arrow-btn {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  background-color: transparent;
  border: none;
  font-size: 2rem;
  cursor: pointer;
  z-index: 10;
  color: #333;
  transition: color 0.3s ease;
}

#prevBtn {
  left: -40px; /* Adjust as needed */
}

#nextBtn {
  right: -40px; /* Adjust as needed */
}

@media (max-width: 768px) {
  #prevBtn, #nextBtn {
    top: auto;
    bottom: -40px;
    transform: none;
    left: 30%;
    right: 30%;
  }
}
.team-carousel {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 20px;
  margin-top: 40px;
}

.team-member {
  text-align: center;
  opacity: 0.5;
  margin: 15px;
  cursor: pointer;
  transition: transform 0.3s ease, opacity 0.3s ease;
}

.team-member:hover {
  transform: scale(1.08);
  opacity: 1 !important;
}


.team-member.active {
  opacity: 1;
  transform: scale(1.05);
}

.team-member img {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  object-fit: cover;
}

.name {
  font-weight: bold;
  margin-top: 10px;
}

.title {
  font-size: 0.9rem;
  color: #777;
}


  </style>
</head>
<body>

<!-- Hero Section (Full Width with Background Image) -->
<section class="w-100 text-center text-white py-5 m-0 mb-5" style="background: url('../assets/img/backgrounds/districtOnebg.png') no-repeat center center / cover;">
  <div class="w-100">
    <h1 class="display-4 fw-bold">About DistrictOne</h1>
    <p class="lead mt-3" style="text-align: center;">Learn more about the DistrictOne and the team behind its development.
</p>
    <a href="#profile-0" class="btn btn-outline-light mt-4 px-4 py-2 rounded-pill shadow-sm">Get to Know Us</a>
  </div>
</section>



<div class="text-center mb-5">
    <h1 class="display-5 fw-bold">Meet the Development Team</h1>
    <p class="text-muted">Get to know the developers behind DistrictOne.</p>
  </div>

<!-- Arrow Buttons -->
<div class="profile-wrapper">
  <button class="arrow-btn" id="prevBtn">&#8592;</button>
  <button class="arrow-btn" id="nextBtn">&#8594;</button>

<!-- Profiles -->
<div class="profile-slide active profile-container" id="profile-0">
  <div class="profile-image">
    <img src="../assets/img/avatars/profile1.png" alt="Jonathan Dave Aquino Fajarda">
  </div>
  <div class="profile-text">
    <div class="title" style="font-size:28px; text-align: center;">Engr. Jonathan Dave A. Fajarda</div>
    <div class="title" style="font-size:28px; text-align: center;"> MPA, ECE</div>
    <div class="subtitle">Project Manager/ SCRUM Master</div>
    <div class="description" style="text-align: justify;">
      Engr. Jonathan Dave A. Fajarda is the Supervising Lead of the Management Information Services Section 
      of Calamba Water District who envisioned the Development of DistrictOne. He is the Project Manager and 
      Scrum Master for the system who oversaw the entire project lifecycle from initiation to completion. He 
      translated CWD's business needs and requirements into clear technical specifications, designed the system 
      architecture, and led the whole developemnt process using Agile-Scrum Methodology. 
    </div>
  </div>
</div>

<div class="profile-slide profile-container" id="profile-1">
  <div class="profile-image">
    <img src="../assets/img/avatars/profile2.png" alt="Andrae Sebastean O. Alegre">
  </div>
  <div class="profile-text">
    <div class="title">Andrae Sebastean O. Alegre</div>
    <div class="subtitle">Full Stack Developer/ DBA</div>
    <div class="description" style="text-align: justify;">
      Mr. Andrae Sebastean O. Alegre is the Full Stack Developer and Database Administrator (DBA) of DistrictOne. 
      He works on both the front-end and back-end parts of the website, ensuring that the system are not only 
      user-friendly but also efficient and reliable. At DistrictOne, Andrae plays a key role in developing and 
      maintaining online systems that users can easily interact with.
    </div>
  </div>
</div>

<div class="profile-slide profile-container" id="profile-2">
  <div class="profile-image">
    <img src="../assets/img/avatars/profile4.png" alt="Joshua Scieryl Glaraga">
  </div>
  <div class="profile-text">
    <div class="title">Joshua Scieryl Glaraga</div>
    <div class="subtitle">Backend Developer/ Server Administrator/ DBA</div>
    <div class="description" style="text-align: justify;">
      Mr. Joshua Scieryl G. Glaraga is a dedicated PHP developer, committed to building efficient and 
      reliable web solutions. His role at DistrictOne includes working as a Backend Developer, Server Administrator, 
      and Database Administrator (DBA), where he ensures that the server infrastructure runs smoothly and that the 
      backend systems are both stable and scalable. Joshua is passionate about optimizing system performance and 
      delivering solid solutions that meet user needs while supporting the overall functionality of DistrictOne.
    </div>
  </div>
</div>

<div class="profile-slide profile-container" id="profile-3">
  <div class="profile-image">
    <img src="../assets/img/avatars/profile3.png" alt="Hannah G. Torres">
  </div>
  <div class="profile-text">
    <div class="title">Hannah G. Torres</div>
    <div class="subtitle">UI Designer/ Backend Developer/ DBA</div>
    <div class="description" style="text-align: justify;">
    Ms. Hannah G. Torres has an expertise in UX/UI design, backend development, and database administration (DBA).
     As part of the DistrictOne team, she plays a crucial role in designing user-friendly interfaces, ensuring a 
     seamless user experience, and developing the backend systems that support the system's functionality. Her work 
     focuses on creating visually appealing, intuitive designs while also managing and optimizing the database to 
     ensure the system runs efficiently and reliably.
    </div>
  </div>
</div>

<div class="team-carousel">
  <div class="team-member" data-index="0">
    <img src="../assets/img/avatars/picture1.jpg" alt="Jonathan Dave Aquino Fajarda" />
    <p class="name">Engr. Jonathan Dave A. Fajarda</p>
    <p class ="name">  MPA, ECE </p>
    <p class="title">Project Manager/ SCRUM Master</p>
  </div>
  <div class="team-member" data-index="1">
    <img src="../assets/img/avatars/profile21.png" alt="Andrae Sebastean O. Alegre" />
    <p class="name">Andrae Sebastean O. Alegre</p>
    <p class="title">Full Stack Developer/ DBA</p>
  </div>
  <div class="team-member" data-index="2">
    <img src="../assets/img/avatars/profile41.png" alt="Joshua Scieryl Glaraga" />
    <p class="name">Joshua Scieryl G. Glaraga</p>
    <p class="title">Backend Developer/</p>
    <p class="title">  Server Administrator/ DBA</p>
  </div>
  <div class="team-member" data-index="3">
    <img src="../assets/img/avatars/profile31.png" alt="Hannah G. Torres" />
    <p class="name">Hannah G. Torres</p>
    <p class="title">UI Designer/</p>
    <p class="title">   Backend Developer/ DBA</p>
  </div>
</div>

</div>

<!-- About DistrictOne Section Matching Team Format -->
<div class="profile-wrapper my-5">
  <div class="profile-container" id="profile-districtone">
    <div class="profile-image">
      <img src="../assets/img/favicon/districtone.png" alt="DistrictOne System">
    </div>
    <div class="profile-text">
      <div class="title" style="text-align: center;">DistrictOne</div>
      <div class="subtitle" style="text-align: center;">Calamba Water District Management Information System</div>
      <div class="description" style="text-align: justify;">
        Calamba Water District Management Information System, also known as <strong>'DistrictOne'</strong>, 
        is an integrated information platform developed by the Management Information Services Section of 
        Calamba Water District (CWD). It centralizes and streamlines internal operations, providing employees
         with seamless access to essential services. The core of the system is the Employee Login Portal, which 
         enables secure access to a suite of tools from announcements and activity calendars to filing leaves, 
         room reservations, and SALNs. It also includes features for IT support requests via a built-in ticketing system.
          Backed by CWD’s leadership, the system will continue to evolve with ongoing enhancements led by the MIS 
          Development Team. DistrictOne stands as a digital pillar of efficiency, transparency, and modernization 
          for Calamba Water District
      </div>
    </div>
  </div>
</div>





<script>
const profiles = document.querySelectorAll('.profile-slide');
const members = document.querySelectorAll('.team-member');
let currentIndex = 0;

function updateProfiles() {
  profiles.forEach((profile, i) => {
    profile.classList.toggle('active', i === currentIndex);
  });
  members.forEach((member, i) => {
    member.classList.toggle('active', i === currentIndex);
  });
}

document.getElementById('prevBtn').addEventListener('click', () => {
  currentIndex = (currentIndex - 1 + profiles.length) % profiles.length;
  updateProfiles();
});

document.getElementById('nextBtn').addEventListener('click', () => {
  currentIndex = (currentIndex + 1) % profiles.length;
  updateProfiles();
});

members.forEach(member => {
  member.addEventListener('click', () => {
    currentIndex = parseInt(member.dataset.index);
    updateProfiles();
  });
});

updateProfiles(); // Initial call

  let interval;

  function showSlide(index) {
    slides.forEach((slide, i) => {
      slide.classList.remove('active');
      if (i === index) {
        slide.classList.add('active');
        const img = slide.querySelector('img');
        const text = slide.querySelector('.profile-text');
        img.style.animation = 'none';
        text.style.animation = 'none';
        void img.offsetWidth; // reflow
        void text.offsetWidth;
        img.style.animation = 'slideIn 1s ease forwards';
        text.style.animation = 'fadeInUp 1s ease forwards';
        text.style.animationDelay = '0.2s';
      }
    });
  }

  function nextSlide() {
    currentIndex = (currentIndex + 1) % slides.length;
    showSlide(currentIndex);
  }

  function prevSlide() {
    currentIndex = (currentIndex - 1 + slides.length) % slides.length;
    showSlide(currentIndex);
  }

  document.getElementById('nextBtn').addEventListener('click', () => {
    nextSlide();
    resetInterval();
  });

  document.getElementById('prevBtn').addEventListener('click', () => {
    prevSlide();
    resetInterval();
  });

  function resetInterval() {
    clearInterval(interval);
    interval = setInterval(nextSlide, 5000);
  }

  // Start auto-slide
  interval = setInterval(nextSlide, 5000);

  
</script>

<script>

  
</script>
</body>
</html>