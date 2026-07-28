const { chromium } = require('playwright');
const path = require('path');

async function run() {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1440, height: 900 });

  const files = [
    {
      name: 'direction1',
      url: 'file:///' + path.join(__dirname, 'design-demos', 'direction1_angled_gradient.html').replace(/\\/g, '/'),
      out: 'C:/Users/Sreenandha M S/.gemini/antigravity-ide/brain/06377ff9-ee4a-4faf-a741-8b96941ac33f/direction1.png'
    },
    {
      name: 'direction2',
      url: 'file:///' + path.join(__dirname, 'design-demos', 'direction2_bento_grid.html').replace(/\\/g, '/'),
      out: 'C:/Users/Sreenandha M S/.gemini/antigravity-ide/brain/06377ff9-ee4a-4faf-a741-8b96941ac33f/direction2.png'
    },
    {
      name: 'direction3',
      url: 'file:///' + path.join(__dirname, 'design-demos', 'direction3_minimalist_ive.html').replace(/\\/g, '/'),
      out: 'C:/Users/Sreenandha M S/.gemini/antigravity-ide/brain/06377ff9-ee4a-4faf-a741-8b96941ac33f/direction3.png'
    }
  ];

  for (const f of files) {
    console.log(`Navigating to ${f.name} at ${f.url}...`);
    try {
      await page.goto(f.url, { waitUntil: 'load' });
      await page.waitForTimeout(1000); // Allow any CSS transitions to load
      await page.screenshot({ path: f.out, fullPage: true });
      console.log(`Saved screenshot to ${f.out}`);
    } catch (e) {
      console.error(`Error processing ${f.name}:`, e);
    }
  }

  await browser.close();
}

run();
