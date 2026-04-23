const config = {
  type: Phaser.AUTO,
  width: 800,
  height: 600,
  pixelArt: true,
  scene: {
    preload,
    create,
    update
  }
};

const game = new Phaser.Game(config);

let plant;
let plantStage = 1;

function preload() {
  // Plant stages
  this.load.image('plant1', 'assets/plants/stage1.png');
  this.load.image('plant2', 'assets/plants/stage2.png');
  this.load.image('plant3', 'assets/plants/stage3.png');

  // Simple floor tile (you can replace later)
  this.load.image('floor', 'https://labs.phaser.io/assets/tilemaps/tiles/gridtiles.png');
}

function create() {
  // Draw a simple tiled floor
  for (let x = 0; x < 800; x += 32) {
    for (let y = 0; y < 600; y += 32) {
      this.add.image(x, y, 'floor').setOrigin(0);
    }
  }

  // Add plant in center
  plant = this.add.image(400, 300, 'plant1');
  plant.setScale(2);
  plant.setInteractive();

  // Click interaction
  plant.on('pointerdown', () => {
    growPlant();
  });
}

function growPlant() {
  plantStage++;

  if (plantStage > 3) plantStage = 1;

  plant.setTexture('plant' + plantStage);

  console.log("Plant stage:", plantStage);
}

function update() {}