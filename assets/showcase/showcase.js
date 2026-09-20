/* ============================================================
   3D Product Showcase — scroll drives rotation, rotation drives everything

   THE ONE IDEA WORTH READING FIRST
   -------------------------------
   Scroll position maps to an angle: u is measured in whole turns, and
   the bottle's rotation is always u × 360°. Everything else is derived
   from u, which is why the text, the label, the liquid and the dial can
   never drift out of sync with each other.

   Each product gets a full turn of its own, then hands over during the
   next turn. The handover happens at the exact moment the bottle has
   its BACK to the camera, so the label changing is not something you
   can catch happening — the bottle turns away as Black Diamond and
   comes back around as Challenger. One continuous rotation, no cut.

     u:        0         1        1.5        2         3       3.5
     angle:    0°       360°      180°       0°       360°     180°
     product:  |-- 0 spins 360° --|- hands over -|-- 1 spins 360° --|…
                                  ^ swap here            ^ swap here
     text:     [-- visible -------] fade out  fade in [-- visible --]

   So: face the viewer, complete a full rotation, turn away, become the
   next product, come back around. Text fades with the same curve, so
   the words and the glass move together.

   Roles are NOT decided here. Prices arrive already filtered by PHP; if
   a visitor is not an approved distributor, no price was ever sent.
   ============================================================ */

import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

/* ---------------- Setup ---------------- */

const root = document.getElementById('sc-root');
if (!root) throw new Error('showcase: root missing');

const DATA = JSON.parse(document.getElementById('sc-data').textContent);
const PRODUCTS = DATA.products || [];
const N = PRODUCTS.length;

const el = {
    canvas:    document.getElementById('sc-canvas'),
    track:     document.getElementById('sc-track'),
    stage:     document.getElementById('sc-stage'),
    panel:     document.getElementById('sc-panel'),
    count:     document.getElementById('sc-count'),
    cat:       document.getElementById('sc-cat'),
    name:      document.getElementById('sc-name'),
    teaser:    document.getElementById('sc-teaser'),
    priceWrap: document.getElementById('sc-price'),
    priceNow:  document.getElementById('sc-price-now'),
    priceWas:  document.getElementById('sc-price-was'),
    addId:     document.getElementById('sc-add-id'),
    dialFill:  document.getElementById('sc-dial-fill'),
    degrees:   document.getElementById('sc-degrees'),
    hint:      document.getElementById('sc-hint'),
    loading:   document.getElementById('sc-loading'),
    loadFill:  document.getElementById('sc-loading-fill'),
    loadText:  document.getElementById('sc-loading-text'),
    cats:      Array.from(document.querySelectorAll('.sc-cat')),
    fallback:  document.getElementById('sc-fallback'),
    more:      document.getElementById('sc-more'),
    drawer:    document.getElementById('sc-drawer'),
};

/* Scroll geometry.
   HOLD is dead space at each end so the first and last bottles get a
   moment facing you before and after the rotation starts. */
const HOLD = 0.07;

/* Two turns per product — one to show itself off, one to hand over —
   plus a final turn so the last bottle also completes a rotation
   rather than stopping the moment it arrives. */
const SPAN = Math.max(1, 2 * (N - 1) + 1);

const SWAP_DIP = 0.055;
const MIX_WIN = 0.10;

const TAU = Math.PI * 2;
const clamp = (v, a, b) => (v < a ? a : v > b ? b : v);
const lerp = (a, b, t) => a + (b - a) * t;

function smoothstep(edge0, edge1, x) {
    const t = clamp((x - edge0) / (edge1 - edge0), 0, 1);
    return t * t * (3 - 2 * t);
}

/* ---------------- Capability checks ---------------- */

function hasWebGL() {
    try {
        const c = document.createElement('canvas');
        return !!(
            window.WebGLRenderingContext &&
            (c.getContext('webgl2') || c.getContext('webgl'))
        );
    } catch (e) {
        return false;
    }
}

const reducedMotion = window.matchMedia(
    '(prefers-reduced-motion: reduce)'
).matches;

function showFallback(reason) {
    root.classList.remove('is-loading');
    root.classList.add('is-fallback', 'is-ready');

    if (el.fallback) el.fallback.hidden = false;

    if (reason) {
        console.warn(
            '[showcase] falling back to the list view:',
            reason
        );
    }
}

if (!N) {
    showFallback('no products');
    throw new Error('showcase: empty');
}

if (reducedMotion) {
    showFallback('reduced motion requested');
    throw new Error('showcase: reduced motion');
}

if (!hasWebGL()) {
    showFallback('WebGL unavailable');
    throw new Error('showcase: no webgl');
}

root.classList.add('is-loading');

/* Rough device tier. Transmissive glass is the expensive part, so a
   phone gets a cheaper material rather than a slideshow. */
const isCoarse = window.matchMedia('(pointer: coarse)').matches;
const lowPower =
    isCoarse || (navigator.hardwareConcurrency || 4) <= 4;

const QUALITY = lowPower ? 'low' : 'high';

/* ============================================================
   Renderer, camera, lights
   ============================================================ */

const renderer = new THREE.WebGLRenderer({
    canvas: el.canvas,
    antialias: !lowPower,
    alpha: true,
    powerPreference: 'high-performance',
});

renderer.setPixelRatio(
    Math.min(
        window.devicePixelRatio || 1,
        lowPower ? 1.75 : 2
    )
);

renderer.outputColorSpace = THREE.SRGBColorSpace;
renderer.toneMapping = THREE.ACESFilmicToneMapping;
renderer.toneMappingExposure = 1.05;

const scene = new THREE.Scene();

const camera = new THREE.PerspectiveCamera(
    32,
    1,
    0.1,
    100
);

camera.position.set(0, 0, 6.4);

/* A small room, blurred, purely as a reflection source. */
const pmrem = new THREE.PMREMGenerator(renderer);

scene.environment = pmrem.fromScene(
    new RoomEnvironment(),
    0.04
).texture;

const key = new THREE.DirectionalLight(
    0xfff4e6,
    2.1
);

key.position.set(-3.2, 4.5, 3.4);
scene.add(key);

const fill = new THREE.DirectionalLight(
    0xbfd6d0,
    0.55
);

fill.position.set(3.5, -1.2, 2.0);
scene.add(fill);

/* The rim takes the current drink's colour. */
const rim = new THREE.PointLight(
    0xb5561f,
    26,
    14,
    2
);

rim.position.set(2.6, 1.4, -3.2);
scene.add(rim);

scene.add(
    new THREE.AmbientLight(
        0xffffff,
        0.25
    )
);

/* The bottle lives in a pivot so rotation is always about its own axis. */
const pivot = new THREE.Group();
scene.add(pivot);

/* ============================================================
   Label textures
   ============================================================ */

const LABEL_W = 1536;
const LABEL_H = 445;

function labelTexture(canvas) {
    const tex = new THREE.CanvasTexture(canvas);

    tex.colorSpace = THREE.SRGBColorSpace;
    tex.wrapS = THREE.RepeatWrapping;
    tex.offset.x = 0.5;
    tex.anisotropy =
        renderer.capabilities.getMaxAnisotropy();
    tex.needsUpdate = true;

    return tex;
}

function roundRect(ctx, x, y, w, h, r) {
    ctx.beginPath();

    ctx.moveTo(x + r, y);

    ctx.arcTo(
        x + w,
        y,
        x + w,
        y + h,
        r
    );

    ctx.arcTo(
        x + w,
        y + h,
        x,
        y + h,
        r
    );

    ctx.arcTo(
        x,
        y + h,
        x,
        y,
        r
    );

    ctx.arcTo(
        x,
        y,
        x + w,
        y,
        r
    );

    ctx.closePath();
}

/**
 * Draw a label for a procedural product.
 */
function drawGeneratedLabel(product) {
    const c = document.createElement('canvas');

    c.width = LABEL_W;
    c.height = LABEL_H;

    const ctx = c.getContext('2d');

    const panelW = LABEL_W * 0.40;
    const panelX = (LABEL_W - panelW) / 2;
    const panelY = LABEL_H * 0.07;
    const panelH = LABEL_H * 0.86;
    const accent = product.accent;

    ctx.fillStyle = '#12100e';

    roundRect(
        ctx,
        panelX,
        panelY,
        panelW,
        panelH,
        10
    );

    ctx.fill();

    ctx.strokeStyle = accent;
    ctx.lineWidth = 3;

    roundRect(
        ctx,
        panelX + 10,
        panelY + 10,
        panelW - 20,
        panelH - 20,
        6
    );

    ctx.stroke();

    ctx.globalAlpha = 0.45;
    ctx.lineWidth = 1.5;

    roundRect(
        ctx,
        panelX + 20,
        panelY + 20,
        panelW - 40,
        panelH - 40,
        4
    );

    ctx.stroke();

    ctx.globalAlpha = 1;

    const cx = LABEL_W / 2;

    ctx.fillStyle = accent;
    ctx.font =
        '600 20px "Space Grotesk", system-ui, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    const catText =
        product.category
            .toUpperCase()
            .split('')
            .join(' ');

    ctx.fillText(
        catText,
        cx,
        panelY + 62
    );

    ctx.fillStyle = '#f2ece1';

    const words = product.name.split(' ');
    const lines = [];
    let line = '';

    const maxW = panelW - 80;

    let size = 46;

    ctx.font =
        `700 ${size}px "Space Grotesk", system-ui, sans-serif`;

    for (const w of words) {
        const test =
            line ? line + ' ' + w : w;

        if (
            ctx.measureText(test).width > maxW &&
            line
        ) {
            lines.push(line);
            line = w;
        } else {
            line = test;
        }
    }

    if (line) lines.push(line);

    while (lines.length > 3 && size > 26) {
        size -= 4;

        ctx.font =
            `700 ${size}px "Space Grotesk", system-ui, sans-serif`;
    }

    const startY =
        LABEL_H / 2 -
        ((lines.length - 1) * size * 0.58) / 2;

    lines.forEach((l, i) => {
        ctx.fillText(
            l,
            cx,
            startY + i * size * 1.16
        );
    });

    ctx.strokeStyle = accent;
    ctx.globalAlpha = 0.7;
    ctx.lineWidth = 2;

    ctx.beginPath();

    ctx.moveTo(
        cx - 46,
        panelY + panelH - 74
    );

    ctx.lineTo(
        cx + 46,
        panelY + panelH - 74
    );

    ctx.stroke();

    ctx.globalAlpha = 1;

    ctx.fillStyle = '#8d8478';
    ctx.font =
        '500 16px "Space Grotesk", system-ui, sans-serif';

    ctx.fillText(
        'MAAYI INDUSTRIES',
        cx,
        panelY + panelH - 48
    );

    ctx.font =
        '400 13px "Space Grotesk", system-ui, sans-serif';

    ctx.fillText(
        'KASAMA · ZAMBIA',
        cx,
        panelY + panelH - 26
    );

    return labelTexture(c);
}

/**
 * Composite uploaded artwork onto the label band.
 */
function drawUploadedLabel(image) {
    const c = document.createElement('canvas');

    c.width = LABEL_W;
    c.height = LABEL_H;

    const ctx = c.getContext('2d');

    const aspect =
        image.width / image.height;

    if (aspect > 2.4) {
        ctx.drawImage(
            image,
            0,
            0,
            LABEL_W,
            LABEL_H
        );
    } else {
        const maxH = LABEL_H * 0.92;
        const maxW = LABEL_W * 0.44;

        let h = maxH;
        let w = h * aspect;

        if (w > maxW) {
            w = maxW;
            h = w / aspect;
        }

        ctx.drawImage(
            image,
            (LABEL_W - w) / 2,
            (LABEL_H - h) / 2,
            w,
            h
        );
    }

    return labelTexture(c);
}

/* ============================================================
   The procedural bottle
   ============================================================ */

const BOTTLE_H = 2.7;

function bottleProfile(points) {
    return points.map(
        ([x, y]) => new THREE.Vector2(x, y)
    );
}

function buildProceduralBottle() {
    const group = new THREE.Group();

    const glassGeo = new THREE.LatheGeometry(
        bottleProfile([
            [0.00, 0.00],
            [0.40, 0.00],
            [0.425, 0.05],
            [0.425, 1.24],
            [0.415, 1.42],
            [0.34, 1.60],
            [0.215, 1.76],
            [0.168, 1.90],
            [0.163, 2.26],
            [0.188, 2.33],
            [0.186, 2.40],
            [0.00, 2.40],
        ]),
        72
    );

    glassGeo.computeVertexNormals();

    const glassMat =
        QUALITY === 'high'
            ? new THREE.MeshPhysicalMaterial({
                  color: 0x4a3524,
                  roughness: 0.06,
                  metalness: 0,
                  transmission: 0.96,
                  thickness: 0.7,
                  ior: 1.46,
                  transparent: true,
                  side: THREE.DoubleSide,
                  envMapIntensity: 1.4,
              })
            : new THREE.MeshStandardMaterial({
                  color: 0x4a3524,
                  roughness: 0.12,
                  metalness: 0.1,
                  transparent: true,
                  opacity: 0.62,
                  side: THREE.DoubleSide,
                  envMapIntensity: 1.5,
              });

    const glass = new THREE.Mesh(
        glassGeo,
        glassMat
    );

    group.add(glass);

    const liquidGeo = new THREE.LatheGeometry(
        bottleProfile([
            [0.00, 0.03],
            [0.385, 0.03],
            [0.385, 1.22],
            [0.375, 1.40],
            [0.305, 1.58],
            [0.190, 1.74],
            [0.146, 1.86],
            [0.00, 1.86],
        ]),
        64
    );

    liquidGeo.computeVertexNormals();

    const liquidMat =
        QUALITY === 'high'
            ? new THREE.MeshPhysicalMaterial({
                  color: 0xb5561f,
                  roughness: 0.16,
                  metalness: 0,
                  transmission: 0.42,
                  thickness: 1.3,
                  ior: 1.34,
                  transparent: true,
                  envMapIntensity: 0.9,
              })
            : new THREE.MeshStandardMaterial({
                  color: 0xb5561f,
                  roughness: 0.2,
                  metalness: 0,
                  envMapIntensity: 0.8,
              });

    const liquid = new THREE.Mesh(
        liquidGeo,
        liquidMat
    );

    group.add(liquid);

    const capMat =
        new THREE.MeshStandardMaterial({
            color: 0x1c1a17,
            roughness: 0.32,
            metalness: 0.85,
            envMapIntensity: 1.2,
        });

    const cap = new THREE.Mesh(
        new THREE.CylinderGeometry(
            0.185,
            0.185,
            0.30,
            48
        ),
        capMat
    );

    cap.position.y = 2.44;
    group.add(cap);

    const collar = new THREE.Mesh(
        new THREE.CylinderGeometry(
            0.196,
            0.196,
            0.045,
            48
        ),
        capMat
    );

    collar.position.y = 2.28;
    group.add(collar);

    const labelMat =
        new THREE.MeshStandardMaterial({
            roughness: 0.82,
            metalness: 0.02,
            transparent: true,
            side: THREE.DoubleSide,
            envMapIntensity: 0.7,
        });

    const label = new THREE.Mesh(
        new THREE.CylinderGeometry(
            0.432,
            0.432,
            0.80,
            72,
            1,
            true
        ),
        labelMat
    );

    label.position.y = 0.76;
    label.renderOrder = 2;

    group.add(label);

    group.position.y =
        -BOTTLE_H / 2 + 0.28;

    return {
        group,
        glassMat,
        liquidMat,
        capMat,
        labelMat,
        isProcedural: true,
    };
}

/* ============================================================
   Uploaded GLB support
   ============================================================ */

function normaliseModel(object) {
    const box =
        new THREE.Box3().setFromObject(object);

    const size =
        box.getSize(new THREE.Vector3());

    const centre =
        box.getCenter(new THREE.Vector3());

    const scale =
        size.y > 0
            ? BOTTLE_H / size.y
            : 1;

    const group = new THREE.Group();

    object.position.sub(centre);
    object.scale.setScalar(scale);
    object.position.multiplyScalar(scale);

    group.add(object);

    return group;
}

function classifyModel(object) {
    const parts = {
        glassMat: null,
        liquidMat: null,
        capMat: null,
        labelMat: null,
    };

    object.traverse((node) => {
        if (!node.isMesh || !node.material) return;

        node.frustumCulled = false;

        node.material = Array.isArray(node.material)
            ? node.material.map((m) => m.clone())
            : node.material.clone();

        const mat = Array.isArray(node.material)
            ? node.material[0]
            : node.material;

        const name = (
            node.name +
            ' ' +
            (mat.name || '')
        ).toLowerCase();

        if (
            /label|sticker|artwork/.test(name)
        ) {
            mat.transparent = true;
            parts.labelMat = mat;
            node.renderOrder = 2;

        } else if (
            /liquid|juice|fill|drink|wine|water/.test(name)
        ) {
            parts.liquidMat = mat;

        } else if (
            /cap|cork|lid|seal|top/.test(name)
        ) {
            parts.capMat = mat;

        } else if (
            /glass|bottle|body|vessel/.test(name)
        ) {
            parts.glassMat = mat;

            if (
                QUALITY === 'high' &&
                mat.isMeshPhysicalMaterial &&
                !mat.transmission
            ) {
                mat.transmission = 0.9;
                mat.thickness = 0.7;
                mat.transparent = true;
            }
        }
    });

    return parts;
}

/* ============================================================
   Asset loading
   ============================================================ */

const gltfLoader = new GLTFLoader();
const texLoader = new THREE.TextureLoader();

/** Per-product: which mesh to show and which materials to drive. */
const slots = [];

function loadImage(url) {
    return new Promise((resolve, reject) => {
        const img = new Image();

        img.crossOrigin = 'anonymous';

        img.onload = () => resolve(img);

        img.onerror = () =>
            reject(
                new Error(
                    'label failed: ' + url
                )
            );

        img.src = url;
    });
}

/* ============================================================
   BUILD SLOT
   ============================================================ */

async function buildSlot(product, index) {
    let entry;

    if (product.model) {
        try {
            const gltf =
                await gltfLoader.loadAsync(
                    product.model
                );

            const group =
                normaliseModel(gltf.scene);

            entry = Object.assign(
                {
                    group,
                    isProcedural: false,
                },
                classifyModel(group)
            );

        } catch (err) {
            console.warn(
                '[showcase] model failed, using the built-in bottle:',
                product.name,
                err
            );

            entry = buildProceduralBottle();
        }

    } else {
        entry = buildProceduralBottle();
    }

    /* ------------------------------------------------------------
       LABEL OVERRIDE

       overrideLabel OFF:
           Preserve whatever label/texture is already inside the GLB.

       overrideLabel ON:
           Uploaded artwork replaces the GLB label.
           If there is no upload and this is the procedural bottle,
           generate the showcase label.

       This is deliberately gated. The old code would automatically
       replace a GLB label whenever product.label existed.
       ------------------------------------------------------------ */

    let texture = null;

    if (product.overrideLabel) {

        if (product.label) {
            try {
                texture = drawUploadedLabel(
                    await loadImage(product.label)
                );

            } catch (err) {
                console.warn(
                    '[showcase] label image failed:',
                    product.name,
                    err
                );
            }
        }

        if (
            !texture &&
            entry.isProcedural
        ) {
            texture =
                drawGeneratedLabel(product);
        }

        if (
            texture &&
            entry.labelMat
        ) {
            entry.labelMat.map = texture;
            entry.labelMat.needsUpdate = true;
        }
    }

    /* ------------------------------------------------------------
       MATERIAL OVERRIDES

       Each material is independent.

       OFF = preserve exactly what was exported in the GLB.
       ON  = apply the administrator's selected colour.
       ------------------------------------------------------------ */

    let liquid = null;

    if (
        product.overrideLiquid &&
        entry.liquidMat
    ) {
        liquid =
            new THREE.Color(
                product.liquidColor
            );

        entry.liquidMat.color.copy(
            liquid
        );
    }

    if (
        product.overrideGlass &&
        entry.glassMat
    ) {
        entry.glassMat.color.set(
            product.glassTint
        );
    }

    if (
        product.overrideCap &&
        entry.capMat
    ) {
        entry.capMat.color.set(
            product.capColor
        );
    }

    entry.group.visible = false;

    /*
     * Only store a base liquid colour when the liquid override
     * is actually enabled.
     *
     * This is important because the animation loop uses baseLiquid.
     * If the override is OFF, the original GLB liquid material must
     * not be touched during scrolling.
     */
    entry.baseLiquid = liquid;

    entry.overrideLiquid =
        !!product.overrideLiquid;

    pivot.add(entry.group);

    slots[index] = entry;
}

async function loadAll() {
    let done = 0;

    const step = () => {
        done += 1;

        const pct = Math.round(
            (done / N) * 100
        );

        if (el.loadFill) {
            el.loadFill.style.width =
                pct + '%';
        }

        if (el.loadText) {
            el.loadText.textContent =
                pct < 100
                    ? `Loading ${pct}%`
                    : 'Ready';
        }
    };

    await buildSlot(
        PRODUCTS[0],
        0
    );

    step();

    slots[0].group.visible = true;

    start();

    for (let i = 1; i < N; i++) {
        await buildSlot(
            PRODUCTS[i],
            i
        );

        step();
        wake();
    }
}

/* ============================================================
   Layout
   ============================================================ */

let viewH = window.innerHeight;
let perTurn = 900;

function layout() {
    viewH = window.innerHeight;

    perTurn = Math.max(
        620,
        viewH * 0.9
    );

    const usable =
        SPAN * perTurn;

    el.track.style.height =
        Math.round(
            usable /
                (1 - 2 * HOLD) +
                viewH
        ) + 'px';

    const w =
        el.stage.clientWidth;

    const h =
        el.stage.clientHeight;

    renderer.setSize(
        w,
        h,
        false
    );

    camera.aspect =
        w / h;

    const narrow = w < 900;

    camera.fov =
        narrow ? 40 : 32;

    pivot.position.x =
        narrow
            ? 0
            : Math.min(
                  1.75,
                  w * 0.00105 + 0.55
              );

    baseY =
        narrow ? 0.42 : 0;

    pivot.position.y = baseY;

    camera.updateProjectionMatrix();
}

function trackProgress() {
    const rect =
        el.track.getBoundingClientRect();

    const scrollable =
        el.track.offsetHeight -
        viewH;

    if (scrollable <= 0) return 0;

    return clamp(
        -rect.top / scrollable,
        0,
        1
    );
}

/* ============================================================
   State driven by scroll
   ============================================================ */

let targetU = 0;
let renderU = 0;

let activeIndex = -1;
let visibleIndex = -1;

let lastAccent = '';

let baseY = 0;

let running = false;
let inView = true;
let idleFrames = 0;

const tmpColorA =
    new THREE.Color();

const tmpColorB =
    new THREE.Color();

function wake() {
    idleFrames = 0;
}

function readScroll() {
    const p = trackProgress();

    const q =
        clamp(
            (p - HOLD) /
                (1 - 2 * HOLD),
            0,
            1
        );

    targetU =
        q * SPAN;

    if (p > 0.002) {
        root.classList.add(
            'is-scrolled'
        );
    }

    idleFrames = 0;
}

/* ---------------- Panel content ---------------- */

function applyProduct(index) {
    const p = PRODUCTS[index];

    if (!p) return;

    el.count.textContent =
        String(index + 1).padStart(
            2,
            '0'
        );

    el.cat.textContent =
        p.category;

    el.name.textContent =
        p.name;

    el.teaser.textContent =
        p.teaser;

    if (
        el.priceNow &&
        p.price
    ) {
        el.priceNow.textContent =
            p.price;

        if (el.priceWas) {
            el.priceWas.hidden =
                !p.wasPrice;

            el.priceWas.textContent =
                p.wasPrice || '';
        }
    }

    if (el.addId) {
        el.addId.value = p.id;
    }

    el.cats.forEach((btn) => {
        const isAll =
            btn.dataset.all === '1';

        const target =
            Number(
                btn.dataset.index
            );

        const match =
            isAll
                ? false
                : PRODUCTS[target] &&
                  PRODUCTS[target].category ===
                      p.category;

        btn.classList.toggle(
            'is-active',
            !!match
        );
    });

    if (
        el.drawer &&
        el.drawer.classList.contains(
            'is-open'
        )
    ) {
        fillDrawer(index);
    }
}

/* ============================================================
   The frame
   ============================================================ */

function frame() {
    if (!running) return;

    requestAnimationFrame(frame);

    if (
        !inView ||
        document.hidden
    ) {
        return;
    }

    const delta =
        targetU - renderU;

    renderU += delta * 0.12;

    if (
        Math.abs(delta) <
        0.00004
    ) {
        renderU = targetU;
        idleFrames += 1;
    } else {
        idleFrames = 0;
    }

    if (idleFrames > 6) {
        return;
    }

    const u = renderU;

    /* Which product owns this angle. */
    const index =
        clamp(
            Math.floor(
                (u + 0.5) / 2
            ),
            0,
            N - 1
        );

    /* Position inside the product's two turns. */
    const dd =
        u - 2 * index;

    /* Handover depth. */
    const phase =
        dd < 0
            ? -dd
            : dd > 1
                ? dd - 1
                : 0;

    if (
        index !== activeIndex
    ) {
        activeIndex = index;
        applyProduct(index);
    }

    let meshIndex = -1;

    for (
        let i = index;
        i >= 0;
        i--
    ) {
        if (slots[i]) {
            meshIndex = i;
            break;
        }
    }

    if (meshIndex < 0) {
        renderer.render(
            scene,
            camera
        );
        return;
    }

    if (
        meshIndex !== visibleIndex
    ) {
        if (
            visibleIndex >= 0 &&
            slots[visibleIndex]
        ) {
            slots[
                visibleIndex
            ].group.visible = false;
        }

        slots[
            meshIndex
        ].group.visible = true;

        visibleIndex =
            meshIndex;
    }

    const slot =
        slots[meshIndex];

    /* Rotation. */
    pivot.rotation.y =
        u * TAU;

    pivot.rotation.z =
        Math.sin(
            u * TAU
        ) * 0.012;

    pivot.position.y =
        baseY +
        Math.sin(
            u * TAU * 0.5
        ) * 0.03;

    /* Label fade. */
    const nearSwap =
        smoothstep(
            0.5 - SWAP_DIP,
            0.5,
            phase
        );

    if (slot.labelMat) {
        slot.labelMat.opacity =
            1 - nearSwap;
    }

    slot.group.scale.setScalar(
        1 -
            nearSwap * 0.045
    );

    /* ------------------------------------------------------------
       LIQUID CROSS-BLEND

       Only blend liquid colours when THIS product has the liquid
       override enabled.

       Also only blend toward the neighbour when THAT product has
       the liquid override enabled.

       This prevents the animation loop from changing a GLB's
       original liquid material just because another product has
       a selected admin colour.
       ------------------------------------------------------------ */

    const neighbour =
        dd > 1
            ? PRODUCTS[
                  Math.min(
                      index + 1,
                      N - 1
                  )
              ]
            : PRODUCTS[
                  Math.max(
                      index - 1,
                      0
                  )
              ];

    const blend =
        smoothstep(
            0.5 - MIX_WIN,
            0.5,
            phase
        ) * 0.5;

    if (
        slot.liquidMat &&
        slot.overrideLiquid &&
        slot.baseLiquid
    ) {
        tmpColorA.copy(
            slot.baseLiquid
        );

        /*
         * If the neighbour also has a liquid override,
         * cross-blend toward its selected colour.
         *
         * Otherwise keep this product's selected colour.
         */
        if (
            neighbour &&
            neighbour.overrideLiquid
        ) {
            tmpColorB.set(
                neighbour.liquidColor
            );

            slot.liquidMat.color
                .copy(tmpColorA)
                .lerp(
                    tmpColorB,
                    blend
                );
        } else {
            slot.liquidMat.color.copy(
                tmpColorA
            );
        }
    }

    /* Accent remains independent from the material overrides. */
    tmpColorA.set(
        PRODUCTS[index].accent
    );

    tmpColorB.set(
        neighbour.accent
    );

    tmpColorA.lerp(
        tmpColorB,
        blend
    );

    rim.color.copy(
        tmpColorA
    );

    const accentHex =
        '#' +
        tmpColorA.getHexString();

    if (
        accentHex !== lastAccent
    ) {
        root.style.setProperty(
            '--sc-accent',
            accentHex
        );

        lastAccent =
            accentHex;
    }

    /* Text transition. */
    const alpha =
        1 -
        smoothstep(
            0.12,
            0.46,
            phase
        );

    const shift =
        (
            dd < 0
                ? dd
                : dd > 1
                    ? dd - 1
                    : 0
        ) * 26;

    el.panel.style.setProperty(
        '--sc-fade',
        alpha.toFixed(3)
    );

    el.panel.style.setProperty(
        '--sc-shift',
        shift.toFixed(1) + 'px'
    );

    el.panel.style.setProperty(
        '--sc-pe',
        alpha < 0.25
            ? 'none'
            : 'auto'
    );

    /* Dial. */
    const deg =
        (
            (u * 360) % 360 +
            360
        ) % 360;

    if (el.dialFill) {
        el.dialFill.style.strokeDasharray =
            `${deg.toFixed(1)} 360`;
    }

    if (el.degrees) {
        el.degrees.textContent =
            Math.round(deg) +
            '\u00B0';
    }

    renderer.render(
        scene,
        camera
    );
}

function start() {
    if (running) return;

    running = true;

    layout();
    readScroll();

    renderU =
        targetU;

    applyProduct(
        clamp(
            Math.floor(
                (targetU + 0.5) / 2
            ),
            0,
            N - 1
        )
    );

    root.classList.remove(
        'is-loading'
    );

    root.classList.add(
        'is-ready'
    );

    requestAnimationFrame(
        frame
    );
}

/* ============================================================
   Input
   ============================================================ */

let ticking = false;

window.addEventListener(
    'scroll',
    () => {
        if (ticking) return;

        ticking = true;

        requestAnimationFrame(
            () => {
                readScroll();
                ticking = false;
            }
        );
    },
    {
        passive: true,
    }
);

window.addEventListener(
    'keydown',
    (e) => {
        if (
            e.defaultPrevented ||
            e.metaKey ||
            e.ctrlKey ||
            e.altKey
        ) {
            return;
        }

        const tag =
            (
                e.target.tagName ||
                ''
            ).toLowerCase();

        if (
            tag === 'input' ||
            tag === 'textarea' ||
            tag === 'select' ||
            e.target.isContentEditable
        ) {
            return;
        }

        if (
            el.drawer &&
            el.drawer.classList.contains(
                'is-open'
            )
        ) {
            return;
        }

        const rect =
            el.track.getBoundingClientRect();

        const onStage =
            rect.top < viewH &&
            rect.bottom > 0;

        if (!onStage) return;

        let step = 0;

        if (
            e.key ===
            'ArrowDown'
        ) {
            step =
                perTurn * 0.09;
        } else if (
            e.key ===
            'ArrowUp'
        ) {
            step =
                -perTurn * 0.09;
        } else {
            return;
        }

        e.preventDefault();

        window.scrollBy({
            top: step,
            behavior: 'smooth',
        });
    }
);

/** Scroll so product `index` is facing the camera. */
function scrollToProduct(index) {
    const q =
        clamp(
            (2 * index) / SPAN,
            0,
            1
        );

    const p =
        HOLD +
        q * (1 - 2 * HOLD);

    const top =
        window.scrollY +
        el.track.getBoundingClientRect()
            .top +
        p *
            (
                el.track.offsetHeight -
                viewH
            );

    window.scrollTo({
        top,
        behavior: 'smooth',
    });
}

el.cats.forEach((btn) => {
    btn.addEventListener(
        'click',
        () => {
            if (
                btn.dataset.all === '1'
            ) {
                const top =
                    window.scrollY +
                    el.track.getBoundingClientRect()
                        .top;

                window.scrollTo({
                    top,
                    behavior: 'smooth',
                });

                return;
            }

            scrollToProduct(
                Number(
                    btn.dataset.index
                )
            );
        }
    );
});

/* ============================================================
   Show more drawer
   ============================================================ */

let lastScrollY = 0;

function fillDrawer(index) {
    const p =
        PRODUCTS[index];

    document.getElementById(
        'sc-drawer-cat'
    ).textContent =
        p.category;

    document.getElementById(
        'sc-drawer-name'
    ).textContent =
        p.name;

    document.getElementById(
        'sc-drawer-body'
    ).innerHTML =
        p.body;

    const link =
        document.getElementById(
            'sc-drawer-link'
        );

    link.href =
        p.permalink;
}

function openDrawer() {
    fillDrawer(
        activeIndex
    );

    lastScrollY =
        window.scrollY;

    el.drawer.hidden = false;

    requestAnimationFrame(
        () =>
            el.drawer.classList.add(
                'is-open'
            )
    );

    document.documentElement.style.overflow =
        'hidden';

    el.more.setAttribute(
        'aria-expanded',
        'true'
    );

    document.getElementById(
        'sc-drawer-close'
    ).focus();
}

function closeDrawer() {
    el.drawer.classList.remove(
        'is-open'
    );

    document.documentElement.style.overflow =
        '';

    window.scrollTo({
        top: lastScrollY,
    });

    el.more.setAttribute(
        'aria-expanded',
        'false'
    );

    el.more.focus();

    setTimeout(
        () => {
            el.drawer.hidden = true;
        },
        320
    );
}

if (el.more) {
    el.more.addEventListener(
        'click',
        openDrawer
    );

    document
        .getElementById(
            'sc-drawer-close'
        )
        .addEventListener(
            'click',
            closeDrawer
        );

    el.drawer.addEventListener(
        'click',
        (e) => {
            if (
                e.target ===
                el.drawer
            ) {
                closeDrawer();
            }
        }
    );

    window.addEventListener(
        'keydown',
        (e) => {
            if (
                e.key === 'Escape' &&
                el.drawer.classList.contains(
                    'is-open'
                )
            ) {
                closeDrawer();
            }
        }
    );
}

/* ============================================================
   Housekeeping
   ============================================================ */

let resizeTimer = null;

window.addEventListener(
    'resize',
    () => {
        clearTimeout(
            resizeTimer
        );

        resizeTimer = setTimeout(
            () => {
                layout();
                readScroll();
                wake();
            },
            120
        );
    }
);

if (
    'IntersectionObserver' in
    window
) {
    new IntersectionObserver(
        (entries) => {
            inView =
                entries[0]
                    .isIntersecting;
        },
        {
            rootMargin: '120px',
        }
    ).observe(el.stage);
}

document.addEventListener(
    'visibilitychange',
    () => {
        if (!document.hidden) {
            readScroll();
            wake();
        }
    }
);

window.addEventListener(
    'pagehide',
    () => {
        running = false;
    }
);

/* ============================================================
   Go
   ============================================================ */

const fontsReady =
    document.fonts
        ? document.fonts
              .load(
                  '700 46px "Space Grotesk"'
              )
              .catch(() => {})
        : Promise.resolve();

fontsReady
    .then(loadAll)
    .catch((err) =>
        showFallback(
            err && err.message
        )
    );