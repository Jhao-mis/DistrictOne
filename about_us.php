<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>About - MIS Section & Development Team | DistrictOne</title>

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="assets/img/favicon/districtone.png" />

  <!-- Bootstrap 5 & FontAwesome 6 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">
  
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    :root {
      --primary-blue: #1a589e;
      --primary-dark: #0f3866;
      --accent-blue: #2563eb;
      --bg-gradient: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    }

    body {
      background: var(--bg-gradient);
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
      color: #334155;
      overflow-x: hidden;
    }

    /* Hero Styling */
    .hero-section {
      background: linear-gradient(135deg, rgba(15, 56, 102, 0.65), rgba(26, 88, 158, 0.45)),
        url('assets/img/backgrounds/districtOnebg.png') no-repeat center center / cover;
      position: relative;
      padding: 100px 20px 80px;
      border-radius: 0 0 40px 40px;
      box-shadow: 0 20px 40px rgba(15, 56, 102, 0.12);
    }

    /* Highlight Card for MIS Section */
    .mis-highlight-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      border: 2px solid rgba(26, 88, 158, 0.2);
      border-radius: 32px;
      box-shadow: 0 25px 50px -12px rgba(26, 88, 158, 0.15);
    }

    .mis-member-card {
      cursor: pointer;
      transition: all 0.3s ease;
      position: relative;
    }

    .mis-member-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 15px 30px rgba(26, 88, 158, 0.12) !important;
      border-color: rgba(26, 88, 158, 0.3) !important;
    }

    .mis-avatar {
      width: 100px;
      height: 100px;
      object-fit: cover;
      border-radius: 50%;
      border: 3px solid #ffffff;
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease;
    }

    .mis-member-card:hover .mis-avatar {
      transform: scale(1.08);
    }

    /* Minimalist Developer Carousel Styling (No Card Box) */
    .arrow-btn {
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      user-select: none;
    }

    .arrow-btn:hover {
      background-color: #2563eb;
      color: #ffffff;
      border-color: #2563eb;
      transform: scale(1.1);
    }

    .arrow-btn:active {
      transform: scale(0.95);
    }

    .profile-slide {
      transition: opacity 0.4s ease-in-out, transform 0.4s ease-in-out;
    }

    .profile-slide.hidden {
      display: none !important;
      opacity: 0;
      transform: translateY(12px);
    }

    .profile-slide.active {
      display: flex !important;
      opacity: 1;
      transform: translateY(0);
    }

    /* Clean Minimalist Thumbnail Selectors */
    .team-member {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      opacity: 0.55;
      filter: grayscale(40%);
    }

    .team-member:hover {
      opacity: 0.9;
      filter: grayscale(0%);
      transform: translateY(-4px);
    }

    .team-member.active-thumb {
      opacity: 1;
      filter: grayscale(0%);
      transform: translateY(-2px);
    }

    .team-member img {
      transition: all 0.3s ease;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .team-member.active-thumb img {
      box-shadow: 0 0 0 3px #2563eb, 0 8px 20px rgba(37, 99, 235, 0.25);
    }

    .badge-role {
      background-color: #eff6ff;
      color: var(--primary-blue);
      font-size: 0.75rem;
      font-weight: 700;
      padding: 5px 14px;
      border-radius: 50px;
      display: inline-block;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    /* ==================== UPGRADED MIS MODAL STYLING ==================== */
    .mis-modal-content {
      border: none !important;
      border-radius: 28px !important;
      overflow: hidden;
      box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.35) !important;
      background: #ffffff;
    }

    .modal-cover-bg {
      height: 110px;
      background: linear-gradient(135deg, #0f3866 0%, #1a589e 50%, #2563eb 100%);
      position: relative;
    }

    .modal-cover-bg .btn-close-custom {
      position: absolute;
      top: 16px;
      right: 16px;
      background: rgba(255, 255, 255, 0.2);
      border: none;
      color: #ffffff;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      backdrop-filter: blur(4px);
      transition: all 0.2s ease;
    }

    .modal-cover-bg .btn-close-custom:hover {
      background: rgba(255, 255, 255, 0.4);
      transform: rotate(90deg);
    }

    .modal-avatar-wrapper {
      margin-top: -60px;
      position: relative;
      display: inline-block;
    }

    .upgraded-modal-avatar {
      width: 120px;
      height: 120px;
      object-fit: cover;
      border-radius: 50%;
      border: 4px solid #ffffff;
      box-shadow: 0 10px 25px rgba(26, 88, 158, 0.25);
      background: #ffffff;
    }

    .skill-tag {
      background: #f1f5f9;
      color: #475569;
      font-size: 0.75rem;
      font-weight: 600;
      padding: 6px 14px;
      border-radius: 20px;
      display: inline-flex;
      margin: 4px;
      align-items: center;
      border: 1px solid #e2e8f0;
      margin-bottom: 6px;
      line-height: 1.4;
    }

    .modal.fade .modal-dialog {
      transform: scale(0.92);
      transition: transform 0.3s ease-out, opacity 0.3s ease-out;
    }

    .modal.show .modal-dialog {
      transform: scale(1);
    }
  </style>
</head>

<body>

  <!-- HERO SECTION -->
  <section class="hero-section text-center text-white mb-5">
    <div class="container max-w-4xl">
      <div
        class="d-flex align-items-center justify-content-center gap-2.5 mb-4 transition-transform duration-300 hover:scale-105">
        <img src="./assets/img/avatars/cwd3d.png" alt="Calamba Water District Logo"
          class="h-16 md:h-20 w-auto object-contain drop-shadow-md" style="max-height: 85px;">
        <span class="d-inline-block bg-white/40 mx-1" style="width: 2px; height: 48px;"></span>
        <img src="./assets/img/avatars/MIS 3D.png" alt="MIS Section Logo"
          class="h-16 md:h-20 w-auto object-contain drop-shadow-md" style="max-height: 85px;">
      </div>

      <h1 class="display-3 font-extrabold tracking-tight drop-shadow-md">About MIS Section <br> & District One</h1>

      <p class="lead mt-3 mx-auto text-white max-w-2xl fw-medium"
        style="max-width: 700px; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">
        Empowering Calamba Water District through innovative digital infrastructure, secure systems management, and
        technical excellence.
      </p>

      <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
        <a href="#mis-section"
          class="btn btn-light text-primary fw-bold px-4 py-2.5 rounded-pill shadow-lg hover:scale-105 transition-all">
          <i class="fa-solid fa-users-gear me-1.5"></i> Explore MIS Section
        </a>
        <a href="#districtone-section"
          class="btn btn-outline-light fw-bold px-4 py-2.5 rounded-pill shadow-lg hover:scale-105 transition-all"
          style="backdrop-filter: blur(8px);">
          <i class="fa-solid fa-laptop-code me-1.5"></i> Explore DistrictOne
        </a>
      </div>
    </div>
  </section>


  <!-- 1. MANAGEMENT INFORMATION SERVICES SECTION -->
  <section class="container max-w-6xl my-5 pt-2" id="mis-section">
    <div class="mis-highlight-card p-4 p-md-5">

      <div class="text-center mb-5">
        <span class="badge-role mb-2" style="background-color: #dbeafe; font-size: 0.85rem;">
          <i class="fa-solid fa-users-gear me-1.5"></i> Core Operational Unit
        </span>
        <h2 class="display-5 font-extrabold text-slate-900">Management Information Services Section</h2>
        <p class="text-muted max-w-2xl mx-auto fs-6 mb-1">
          The dedicated 5-member team managing Calamba Water District's IT operations, system architecture, network
          infrastructure, and digital solutions.
        </p>
        <small class="text-primary fw-semibold"><i class="fa-solid fa-circle-info me-1"></i> Click on any team member
          card to view their full profile.</small>
      </div>

      <div class="row g-4 justify-content-center">

        <!-- Member 1: Dave Fajarda -->
        <div class="col-xl-4 col-md-6">
          <div
            class="mis-member-card bg-white p-4 rounded-4 border border-slate-100 shadow-sm text-center h-100 d-flex flex-column align-items-center"
            data-bs-toggle="modal" data-bs-target="#misModal1">
            <img src="assets/img/avatars/picture1.jpg" alt="Engr. Jonathan Dave A. Fajarda" class="mis-avatar mb-3">
            <h5 class="fw-bold text-dark mb-1">Engr. Jonathan Dave A. Fajarda</h5>
            <p class="text-muted fw-semibold small mb-1"><i class="fa-solid fa-user-tag text-primary me-1"></i> "Dave"
            </p>
            <p class="text-primary fw-semibold small mb-2">MIS Design Specialist A</p>
            <span class="badge bg-primary text-white px-3 py-1.5 rounded-pill small mt-auto">Supervising Lead</span>
          </div>
        </div>

        <!-- Member 2: Lenard Tancangco -->
        <div class="col-xl-4 col-md-6">
          <div
            class="mis-member-card bg-white p-4 rounded-4 border border-slate-100 shadow-sm text-center h-100 d-flex flex-column align-items-center"
            data-bs-toggle="modal" data-bs-target="#misModal2">
            <img src="assets/img/avatars/lt.png" alt="Mr. Lenard Martin G. Tancangco" class="mis-avatar mb-3">
            <h5 class="fw-bold text-dark mb-1">Mr. Lenard Martin G. Tancangco</h5>
            <p class="text-muted fw-semibold small mb-1"><i class="fa-solid fa-user-tag text-primary me-1"></i> "Lenard"
            </p>
            <p class="text-muted fw-semibold small mb-2">Data Controller</p>
            <span class="badge bg-primary-subtle text-primary px-3 py-1.5 rounded-pill small mt-auto">Technical
              Lead</span>
          </div>
        </div>

        <!-- Member 3: Jhao Glaraga -->
        <div class="col-xl-4 col-md-6">
          <div
            class="mis-member-card bg-white p-4 rounded-4 border border-slate-100 shadow-sm text-center h-100 d-flex flex-column align-items-center"
            data-bs-toggle="modal" data-bs-target="#misModal3">
            <img src="assets/img/avatars/jg.png" alt="Mr. Joshua Scieryl G. Glaraga" class="mis-avatar mb-3">
            <h5 class="fw-bold text-dark mb-1">Mr. Joshua Scieryl G. Glaraga</h5>
            <p class="text-muted fw-semibold small mb-1"><i class="fa-solid fa-user-tag text-primary me-1"></i> "Jhao"
            </p>
            <p class="text-muted fw-semibold small mb-2">Job Order Support</p>
            <span class="badge bg-primary-subtle text-primary px-3 py-1.5 rounded-pill small mt-auto">Systems &
              Infrastructure</span>
          </div>
        </div>

        <!-- Member 4: Carlo Oboza -->
        <div class="col-xl-4 col-md-6">
          <div
            class="mis-member-card bg-white p-4 rounded-4 border border-slate-100 shadow-sm text-center h-100 d-flex flex-column align-items-center"
            data-bs-toggle="modal" data-bs-target="#misModal4">
            <img src="assets/img/avatars/co.png" alt="Mr. John Carlo E. Oboza" class="mis-avatar mb-3">
            <h5 class="fw-bold text-dark mb-1">Mr. John Carlo E. Oboza</h5>
            <p class="text-muted fw-semibold small mb-1"><i class="fa-solid fa-user-tag text-primary me-1"></i> "Carlo"
            </p>
            <p class="text-muted fw-semibold small mb-2">Job Order Support</p>
            <span class="badge bg-primary-subtle text-primary px-3 py-1.5 rounded-pill small mt-auto">Hardware & Network
              Support</span>
          </div>
        </div>

        <!-- Member 5: Sasik Marvida -->
        <div class="col-xl-4 col-md-6">
          <div
            class="mis-member-card bg-white p-4 rounded-4 border border-slate-100 shadow-sm text-center h-100 d-flex flex-column align-items-center"
            data-bs-toggle="modal" data-bs-target="#misModal5">
            <img src="assets/img/avatars/dm.png" alt="Mr. Dranreb A. Marvida" class="mis-avatar mb-3">
            <h5 class="fw-bold text-dark mb-1">Mr. Dranreb A. Marvida</h5>
            <p class="text-muted fw-semibold small mb-1"><i class="fa-solid fa-user-tag text-primary me-1"></i> "Sasik"
            </p>
            <p class="text-muted fw-semibold small mb-2">Job Order Support</p>
            <span class="badge bg-primary-subtle text-primary px-3 py-1.5 rounded-pill small mt-auto">Web Dev &
              Multimedia</span>
          </div>
        </div>

      </div>
    </div>
  </section>


  <!-- 2. DISTRICTONE DEVELOPMENT TEAM CAROUSEL (MINIMALIST PORTRAIT) -->
  <section id="districtone-section" class="py-10">
    <!-- Section Header -->
    <div class="text-center mb-10">
      <h1 class="text-3xl md:text-4xl font-extrabold text-slate-900 tracking-tight">Meet the Development Team</h1>
      <p class="text-slate-500 max-w-md mx-auto text-sm md:text-base mt-1.5">Get to know the developers behind DistrictOne.</p>
    </div>

    <!-- Main Profile Carousel Section -->
    <div class="profile-wrapper max-w-5xl mx-auto px-6 md:px-12">

      <!-- Profile Slide 0: Engr. Fajarda -->
      <div class="profile-slide active flex flex-col md:flex-row gap-8 md:gap-12 items-center py-4" id="profile-0">
        <div class="profile-image shrink-0 relative">
          <div class="absolute -inset-2 rounded-3xl bg-blue-100/60 blur-lg -z-10"></div>
          <!-- Portrait Aspect Ratio (w-48 h-64 / md:w-56 md:h-72) -->
          <img src="assets/img/avatars/profile1nb.png" alt="Jonathan Dave Aquino Fajarda" class="w-60 h-80 md:w-72 md:h-96 object-cover rounded-2xl shadow-xl">
        </div>
        <div class="profile-text flex-1">
          <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900 text-center md:text-left leading-snug">
            Engr. Jonathan Dave A. Fajarda
          </h2>
          <div class="text-lg md:text-xl font-bold text-blue-600 text-center md:text-left mt-0.5">
            <i class="fa-solid fa-graduation-cap me-1"></i>MPA, ECE
          </div>
          <div class="text-xs md:text-sm font-bold text-slate-400 uppercase tracking-wider text-center md:text-left mt-1 mb-4">
            Project Manager / SCRUM Master
          </div>
          <p class="text-slate-600 text-sm md:text-base leading-relaxed text-justify">
            Engr. Jonathan Dave A. Fajarda is the Supervising Lead of the Management Information Services Section
            of Calamba Water District who envisioned the Development of DistrictOne. He is the Project Manager and
            Scrum Master for the system who oversaw the entire project lifecycle from initiation to completion. He
            translated CWD's business needs and requirements into clear technical specifications, designed the system
            architecture, and led the whole development process using Agile-Scrum Methodology.
          </p>
        </div>
      </div>

      <!-- Profile Slide 1: Andrae Alegre -->
      <div class="profile-slide flex flex-col md:flex-row gap-8 md:gap-12 items-center py-4 hidden" id="profile-1">
        <div class="profile-image shrink-0 relative">
          <div class="absolute -inset-2 rounded-3xl bg-blue-100/60 blur-lg -z-10"></div>
          <img src="assets/img/avatars/profile3nb.png" alt="Andrae Sebastean O. Alegre" class="w-60 h-80 md:w-72 md:h-96 object-cover rounded-2xl shadow-xl">
        </div>
        <div class="profile-text flex-1">
          <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900 text-center md:text-left leading-snug">
            Andrae Sebastean O. Alegre
          </h2>
          <div class="text-xs md:text-sm font-bold text-blue-600 uppercase tracking-wider text-center md:text-left mt-1 mb-4">
            <i class="fa-solid fa-code me-1"></i> Full Stack Developer / DBA
          </div>
          <p class="text-slate-600 text-sm md:text-base leading-relaxed text-justify">
            Mr. Andrae Sebastean O. Alegre is the Full Stack Developer and Database Administrator (DBA) of DistrictOne.
            He works on both the front-end and back-end parts of the website, ensuring that the system are not only
            user-friendly but also efficient and reliable. At DistrictOne, Andrae plays a key role in developing and
            maintaining online systems that users can easily interact with.
          </p>
        </div>
      </div>

      <!-- Profile Slide 2: Joshua Glaraga -->
      <div class="profile-slide flex flex-col md:flex-row gap-8 md:gap-12 items-center py-4 hidden" id="profile-2">
        <div class="profile-image shrink-0 relative">
          <div class="absolute -inset-2 rounded-3xl bg-blue-100/60 blur-lg -z-10"></div>
          <img src="assets/img/avatars/profile2nb.png" alt="Joshua Scieryl Glaraga" class="w-60 h-80 md:w-72 md:h-96 object-cover rounded-2xl shadow-xl">
        </div>
        <div class="profile-text flex-1">
          <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900 text-center md:text-left leading-snug">
            Joshua Scieryl G. Glaraga
          </h2>
          <div class="text-xs md:text-sm font-bold text-blue-600 uppercase tracking-wider text-center md:text-left mt-1 mb-4">
            <i class="fa-solid fa-server me-1"></i> Backend Developer / Server Administrator / DBA
          </div>
          <p class="text-slate-600 text-sm md:text-base leading-relaxed text-justify">
            Mr. Joshua Scieryl G. Glaraga is a dedicated PHP developer, committed to building efficient and
            reliable web solutions. His role at DistrictOne includes working as a Backend Developer, Server Administrator,
            and Database Administrator (DBA), where he ensures that the server infrastructure runs smoothly and that the
            backend systems are both stable and scalable. Joshua is passionate about optimizing system performance and
            delivering solid solutions that meet user needs while supporting the overall functionality of DistrictOne.
          </p>
        </div>
      </div>

      <!-- Profile Slide 3: Hannah Torres -->
      <div class="profile-slide flex flex-col md:flex-row gap-8 md:gap-12 items-center py-4 hidden" id="profile-3">
        <div class="profile-image shrink-0 relative">
          <div class="absolute -inset-2 rounded-3xl bg-blue-100/60 blur-lg -z-10"></div>
          <img src="assets/img/avatars/profile4nb.png" alt="Hannah G. Torres" class="w-60 h-80 md:w-72 md:h-96 object-cover rounded-2xl shadow-xl">
        </div>
        <div class="profile-text flex-1">
          <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900 text-center md:text-left leading-snug">
            Hannah G. Torres
          </h2>
          <div class="text-xs md:text-sm font-bold text-blue-600 uppercase tracking-wider text-center md:text-left mt-1 mb-4">
            <i class="fa-solid fa-pen-nib me-1"></i> UI Designer / Backend Developer / DBA
          </div>
          <p class="text-slate-600 text-sm md:text-base leading-relaxed text-justify">
            Ms. Hannah G. Torres has an expertise in UX/UI design, backend development, and database administration (DBA).
            As part of the DistrictOne team, she plays a crucial role in designing user-friendly interfaces, ensuring a
            seamless user experience, and developing the backend systems that support the system's functionality. Her work
            focuses on creating visually appealing, intuitive designs while also managing and optimizing the database to
            ensure the system runs efficiently and reliably.
          </p>
        </div>
      </div>

      <!-- NAV BUTTONS IN BETWEEN DESCRIPTION AND THUMBNAILS -->
      <div class="flex items-center justify-center gap-4 my-6 px-4">
        <button class="arrow-btn w-10 h-10 flex items-center justify-center bg-white text-slate-700 hover:bg-blue-600 hover:text-white rounded-full shadow-md border border-slate-200/80" id="prevBtn" aria-label="Previous Profile">
          <i class="fa-solid fa-arrow-left text-sm"></i>
        </button>


        <button class="arrow-btn w-10 h-10 flex items-center justify-center bg-white text-slate-700 hover:bg-blue-600 hover:text-white rounded-full shadow-md border border-slate-200/80" id="nextBtn" aria-label="Next Profile">
          <i class="fa-solid fa-arrow-right text-sm"></i>
        </button>
      </div>

      <!-- Team Minimalist Thumbnails -->
      <div class="team-carousel grid grid-cols-2 sm:grid-cols-4 gap-6 pt-2">
        <div class="team-member active-thumb cursor-pointer text-center" data-index="0">
          <img src="assets/img/avatars/picture1.jpg" alt="Jonathan Dave Aquino Fajarda" class="w-20 h-20 mx-auto rounded-full object-cover mb-2.5" />
          <p class="name font-bold text-xs text-slate-900 line-clamp-1">Engr. Jonathan Dave A. Fajarda</p>
          <p class="text-[11px] font-bold text-blue-600">MPA, ECE</p>
          <p class="title text-[11px] text-slate-500 line-clamp-1 mt-0.5">Project Manager / SCRUM Master</p>
        </div>

        <div class="team-member cursor-pointer text-center" data-index="1">
          <img src="assets/img/avatars/ae.png" alt="Andrae Sebastean O. Alegre" class="w-20 h-20 mx-auto rounded-full object-cover mb-2.5" />
          <p class="name font-bold text-xs text-slate-900 line-clamp-1">Andrae Sebastean O. Alegre</p>
          <p class="title text-[11px] text-slate-500 line-clamp-1 mt-1">Full Stack Developer / DBA</p>
        </div>

        <div class="team-member cursor-pointer text-center" data-index="2">
          <img src="assets/img/avatars/jg.png" alt="Joshua Scieryl Glaraga" class="w-20 h-20 mx-auto rounded-full object-cover mb-2.5" />
          <p class="name font-bold text-xs text-slate-900 line-clamp-1">Joshua Scieryl G. Glaraga</p>
          <p class="title text-[11px] text-slate-500 line-clamp-1 mt-1">Backend Developer / Server Admin</p>
        </div>

        <div class="team-member cursor-pointer text-center" data-index="3">
          <img src="assets/img/avatars/ht.png" alt="Hannah G. Torres" class="w-20 h-20 mx-auto rounded-full object-cover mb-2.5" />
          <p class="name font-bold text-xs text-slate-900 line-clamp-1">Hannah G. Torres</p>
          <p class="title text-[11px] text-slate-500 line-clamp-1 mt-1">UI Designer / Backend Developer</p>
        </div>
      </div>
    </div>
  </section>


  <!-- 3. ABOUT DISTRICTONE PLATFORM SECTION -->
  <section class="max-w-5xl mx-auto px-6 md:px-12 my-16 pt-8 border-t border-slate-200/80">
    <div class="flex flex-col md:flex-row gap-8 md:gap-12 items-center" id="profile-districtone">
      
      <div class="shrink-0 relative">
        <div class="absolute -inset-3 rounded-full bg-blue-200/50 blur-xl -z-10"></div>
        <img src="assets/img/favicon/districtone.png" alt="DistrictOne System" class="w-48 h-48 md:w-60 md:h-60 object-contain drop-shadow-md">
      </div>

      <div class="flex-1">
        <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight text-center md:text-left text-slate-900">
          About <span class="text-blue-600">DistrictOne</span>
        </h2>
        <div class="text-xs md:text-sm font-semibold text-slate-400 uppercase tracking-widest text-center md:text-left mt-1 mb-4">
          Calamba Water District Management Information System
        </div>
        <div class="text-slate-600 text-sm md:text-base leading-relaxed text-justify">
          Calamba Water District Management Information System, also known as <strong class="text-slate-900 font-semibold">'DistrictOne'</strong>,
          is an integrated information platform developed by the Management Information Services Section of
          Calamba Water District (CWD). It centralizes and streamlines internal operations, providing employees
          with seamless access to essential services. The core of the system is the Employee Login Portal, which
          enables secure access to a suite of tools from announcements and activity calendars to filing leaves,
          room reservations, and SALNs. It also includes features for IT support requests via a built-in ticketing
          system.
          <br><br>
          Backed by CWD’s leadership, the system will continue to evolve with ongoing enhancements led by the MIS
          Development Team. DistrictOne stands as a digital pillar of efficiency, transparency, and modernization
          for Calamba Water District.
        </div>
      </div>
    </div>
  </section>


  <!-- FOOTER ACTION -->
  <div class="text-center my-8 pb-4">
    <a href="login.php" class="btn btn-outline-secondary px-4 py-2.5 rounded-pill shadow-sm">
      <i class="fa-solid fa-arrow-left me-2"></i> Back to Employee Login
    </a>
  </div>


  <!-- ==================== UPGRADED POPUP MODALS FOR MIS MEMBERS ==================== -->

  <!-- Modal 1: Engr. Jonathan Dave Fajarda -->
  <div class="modal fade" id="misModal1" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content mis-modal-content">

        <div class="modal-cover-bg">
          <button type="button" class="btn-close-custom" data-bs-dismiss="modal" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <div class="modal-body text-center pt-0 px-4 pb-4">
          <div class="modal-avatar-wrapper mb-3">
            <img src="assets/img/avatars/picture1.jpg" alt="Engr. Jonathan Dave A. Fajarda"
              class="upgraded-modal-avatar">
          </div>

          <h4 class="fw-bold text-slate-900 mb-1">Engr. Jonathan Dave A. Fajarda</h4>
          <p class="text-muted fw-semibold small mb-2"><i class="fa-solid fa-user-tag me-1"></i> Preferred Name:
            <strong>Dave</strong>
          </p>

          <span class="badge bg-primary text-white px-3 py-2 rounded-pill shadow-sm mb-3">
            <i class="fa-solid fa-user-shield me-1"></i> MIS Design Specialist A / Supervising Lead
          </span>

          <div class="bg-light p-3 rounded-4 text-start mb-3 border border-slate-100">
            <p class="text-secondary small leading-relaxed mb-0" style="text-align: justify;">
              Engr. Jonathan Dave A. Fajarda serves as the Supervising Lead of the Management Information Services
              Section at Calamba Water District. He directs agency-wide IT strategy, system architecture planning,
              project lifecycles, and oversees team development efforts across all digital software initiatives.
            </p>
          </div>

          <div class="d-flex flex-wrap justify-content-center gap-1.5 mb-3">
            <span class="skill-tag"><i class="fa-solid fa-diagram-project text-primary me-1"></i> System
              Architecture</span>
            <span class="skill-tag"><i class="fa-solid fa-shield-halved text-primary me-1"></i> IT Supervisory</span>
            <span class="skill-tag"><i class="fa-solid fa-tasks text-primary me-1"></i> Project Management</span>
          </div>

          <div class="pt-2 border-top border-slate-100 d-flex justify-content-around text-muted small">
            <div><i class="fa-solid fa-location-dot text-primary me-1"></i> 4th Flr OGM Dept</div>
            <div><i class="fa-solid fa-phone text-primary me-1"></i> Local 4131</div>
          </div>

        </div>
      </div>
    </div>
  </div>


  <!-- Modal 2: Lenard Martin Tancangco -->
  <div class="modal fade" id="misModal2" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content mis-modal-content">

        <div class="modal-cover-bg">
          <button type="button" class="btn-close-custom" data-bs-dismiss="modal" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <div class="modal-body text-center pt-0 px-4 pb-4">
          <div class="modal-avatar-wrapper mb-3">
            <img src="assets/img/avatars/lt.png" alt="Mr. Lenard Martin G. Tancangco" class="upgraded-modal-avatar">
          </div>

          <h4 class="fw-bold text-slate-900 mb-1">Mr. Lenard Martin G. Tancangco</h4>
          <p class="text-muted fw-semibold small mb-2"><i class="fa-solid fa-user-tag me-1"></i> Preferred Name:
            <strong>Lenard</strong>
          </p>

          <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill shadow-sm mb-3">
            <i class="fa-solid fa-network-wired me-1"></i> Data Controller / Technical Lead
          </span>

          <div class="bg-light p-3 rounded-4 text-start mb-3 border border-slate-100">
            <p class="text-secondary small leading-relaxed mb-0" style="text-align: justify;">
              Bringing over 15 years of dedicated service to Calamba Water District, Mr. Lenard Martin G. Tancangco
              serves as the section's overall technical expert. He manages comprehensive network administration,
              infrastructure maintenance, and provides senior technical direction.
            </p>
          </div>

          <div class="d-flex flex-wrap justify-content-center gap-1.5 mb-3">
            <span class="skill-tag"><i class="fa-solid fa-sitemap text-primary me-1"></i> Network Administration</span>
            <span class="skill-tag"><i class="fa-solid fa-screwdriver-wrench text-primary me-1"></i> Technical
              Lead</span>
            <span class="skill-tag"><i class="fa-solid fa-microchip text-primary me-1"></i> Infrastructure
              Maintenance</span>
          </div>

          <div class="pt-2 border-top border-slate-100 d-flex justify-content-around text-muted small">
            <div><i class="fa-solid fa-location-dot text-primary me-1"></i> 4th Flr OGM Dept</div>
            <div><i class="fa-solid fa-phone text-primary me-1"></i> Local 4132</div>
          </div>

        </div>
      </div>
    </div>
  </div>


  <!-- Modal 3: Joshua Scieryl Glaraga -->
  <div class="modal fade" id="misModal3" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content mis-modal-content">

        <div class="modal-cover-bg">
          <button type="button" class="btn-close-custom" data-bs-dismiss="modal" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <div class="modal-body text-center pt-0 px-4 pb-4">
          <div class="modal-avatar-wrapper mb-3">
            <img src="assets/img/avatars/jg.png" alt="Mr. Joshua Scieryl G. Glaraga" class="upgraded-modal-avatar">
          </div>

          <h4 class="fw-bold text-slate-900 mb-1">Mr. Joshua Scieryl G. Glaraga</h4>
          <p class="text-muted fw-semibold small mb-2"><i class="fa-solid fa-user-tag me-1"></i> Preferred Name:
            <strong>Jhao</strong>
          </p>

          <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill shadow-sm mb-3">
            <i class="fa-solid fa-server me-1"></i> Systems Development & Server Specialist
          </span>

          <div class="bg-light p-3 rounded-4 text-start mb-3 border border-slate-100">
            <p class="text-secondary small leading-relaxed mb-0" style="text-align: justify;">
              Mr. Joshua Scieryl G. Glaraga manages in-house server maintenance and backend system architecture. He is
              instrumental in ensuring high system uptime, executing database backups, overseeing server administration,
              and optimizing PHP application performance.
            </p>
          </div>

          <div class="d-flex flex-wrap justify-content-center gap-1.5 mb-3">
            <span class="skill-tag"><i class="fa-solid fa-code text-primary me-1"></i> Systems Development</span>
            <span class="skill-tag"><i class="fa-solid fa-gears text-primary me-1"></i> Backend Support</span>
            <span class="skill-tag"><i class="fa-solid fa-desktop text-primary me-1"></i> Hardware Maintenance</span>
          </div>

          <div class="pt-2 border-top border-slate-100 d-flex justify-content-around text-muted small">
            <div><i class="fa-solid fa-location-dot text-primary me-1"></i> 4th Flr OGM Dept</div>
            <div><i class="fa-solid fa-phone text-primary me-1"></i> Local 4133</div>
          </div>

        </div>
      </div>
    </div>
  </div>


  <!-- Modal 4: John Carlo Oboza -->
  <div class="modal fade" id="misModal4" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content mis-modal-content">

        <div class="modal-cover-bg">
          <button type="button" class="btn-close-custom" data-bs-dismiss="modal" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <div class="modal-body text-center pt-0 px-4 pb-4">
          <div class="modal-avatar-wrapper mb-3">
            <img src="assets/img/avatars/co.png" alt="Mr. John Carlo E. Oboza" class="upgraded-modal-avatar">
          </div>

          <h4 class="fw-bold text-slate-900 mb-1">Mr. John Carlo E. Oboza</h4>
          <p class="text-muted fw-semibold small mb-2"><i class="fa-solid fa-user-tag me-1"></i> Preferred Name:
            <strong>Carlo</strong>
          </p>

          <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill shadow-sm mb-3">
            <i class="fa-solid fa-wrench me-1"></i> Hardware & Network Support Specialist
          </span>

          <div class="bg-light p-3 rounded-4 text-start mb-3 border border-slate-100">
            <p class="text-secondary small leading-relaxed mb-0" style="text-align: justify;">
              Mr. John Carlo E. Oboza provides key technical support in network administration and directly supervises
              system, workstation, and device maintenance and repairs to keep agency operations running smoothly.
            </p>
          </div>

          <div class="d-flex flex-wrap justify-content-center gap-1.5 mb-3">
            <span class="skill-tag"><i class="fa-solid fa-toolbox text-primary me-1"></i> Hardware Repair &
              Maint.</span>
            <span class="skill-tag"><i class="fa-solid fa-wifi text-primary me-1"></i> Network Admin Support</span>
            <span class="skill-tag"><i class="fa-solid fa-headset text-primary me-1"></i> Technical
              Troubleshooting</span>
          </div>

          <div class="pt-2 border-top border-slate-100 d-flex justify-content-around text-muted small">
            <div><i class="fa-solid fa-location-dot text-primary me-1"></i> 4th Flr OGM Dept</div>
            <div><i class="fa-solid fa-phone text-primary me-1"></i> Local 4133</div>
          </div>

        </div>
      </div>
    </div>
  </div>


  <!-- Modal 5: Dranreb Marvida (Sasik) -->
  <div class="modal fade" id="misModal5" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content mis-modal-content">

        <div class="modal-cover-bg">
          <button type="button" class="btn-close-custom" data-bs-dismiss="modal" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <div class="modal-body text-center pt-0 px-4 pb-4">
          <div class="modal-avatar-wrapper mb-3">
            <img src="assets/img/avatars/dm.png" alt="Mr. Dranreb A. Marvida" class="upgraded-modal-avatar">
          </div>

          <h4 class="fw-bold text-slate-900 mb-1">Mr. Dranreb A. Marvida</h4>
          <p class="text-muted fw-semibold small mb-2"><i class="fa-solid fa-user-tag me-1"></i> Preferred Name:
            <strong>Sasik</strong>
          </p>

          <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill shadow-sm mb-3">
            <i class="fa-solid fa-laptop-code me-1"></i> Web Developer & Multimedia Specialist
          </span>

          <div class="bg-light p-3 rounded-4 text-start mb-3 border border-slate-100">
            <p class="text-secondary small leading-relaxed mb-0" style="text-align: justify;">
              Mr. Dranreb A. Marvida engineered the front-end architecture and interface for the new CWD Official
              Website. He also leads graphic design, video editing, and official photography coverage for major CWD
              events and official initiatives.
            </p>
          </div>

          <div class="d-flex flex-wrap justify-content-center gap-1.5 mb-3">
            <span class="skill-tag"><i class="fa-solid fa-globe text-primary me-1"></i> Website Dev & Maintenance</span>
            <span class="skill-tag"><i class="fa-solid fa-wand-magic-sparkles text-primary me-1"></i> Multimedia
              Design</span>
            <span class="skill-tag"><i class="fa-solid fa-camera text-primary me-1"></i> Event Photo Coverage</span>
          </div>

          <div class="pt-2 border-top border-slate-100 d-flex justify-content-around text-muted small">
            <div><i class="fa-solid fa-location-dot text-primary me-1"></i> 4th Flr OGM Dept</div>
            <div><i class="fa-solid fa-envelope text-primary me-1"></i> cwd.dranrebm@gmail.com</div>
          </div>

        </div>
      </div>
    </div>
  </div>


  <!-- BOOTSTRAP 5 JS BUNDLE -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <!-- CAROUSEL JAVASCRIPT LOGIC -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const slides = document.querySelectorAll('.profile-slide');
      const thumbs = document.querySelectorAll('.team-member');
      const prevBtn = document.getElementById('prevBtn');
      const nextBtn = document.getElementById('nextBtn');
      let currentIndex = 0;
      let autoSlideInterval;

      function showSlide(index) {
        slides.forEach((slide, i) => {
          if (i === index) {
            slide.classList.remove('hidden');
            slide.classList.add('active');
          } else {
            slide.classList.add('hidden');
            slide.classList.remove('active');
          }
        });

        thumbs.forEach((thumb, i) => {
          if (i === index) {
            thumb.classList.add('active-thumb');
          } else {
            thumb.classList.remove('active-thumb');
          }
        });

        currentIndex = index;
      }

      function nextSlide() {
        let index = currentIndex + 1;
        if (index >= slides.length) index = 0;
        showSlide(index);
      }

      function prevSlide() {
        let index = currentIndex - 1;
        if (index < 0) index = slides.length - 1;
        showSlide(index);
      }

      thumbs.forEach(thumb => {
        thumb.addEventListener('click', function() {
          const index = parseInt(this.getAttribute('data-index'));
          showSlide(index);
          resetTimer();
        });
      });

      if (prevBtn) {
        prevBtn.addEventListener('click', () => {
          prevSlide();
          resetTimer();
        });
      }

      if (nextBtn) {
        nextBtn.addEventListener('click', () => {
          nextSlide();
          resetTimer();
        });
      }

      function startTimer() {
        autoSlideInterval = setInterval(nextSlide, 6000);
      }

      function resetTimer() {
        clearInterval(autoSlideInterval);
        startTimer();
      }

      // Initialize
      showSlide(0);
      startTimer();
    });
  </script>

</body>

</html>