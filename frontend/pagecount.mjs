import { chromium } from "playwright";
const file = process.argv[2];
const b = await chromium.launch({ args:["--no-sandbox"] });
const p = await b.newPage();
await p.goto("file://" + file, { waitUntil: "networkidle" });
await p.waitForSelector(".pagedjs_page", { timeout: 30000 }).catch(()=>{});
await p.waitForTimeout(1500);
const info = await p.evaluate(() => {
  const pages = [...document.querySelectorAll(".pagedjs_page")];
  return pages.map((pg, i) => ({
    page: i+1,
    hasCover: !!pg.querySelector(".doc-block--cover"),
    textLen: (pg.innerText||"").trim().length,
    sample: (pg.innerText||"").replace(/\s+/g," ").trim().slice(0,40)
  }));
});
console.log("TOTAL PAGES:", info.length);
for (const x of info.slice(0,4)) console.log(JSON.stringify(x));
await b.close();
