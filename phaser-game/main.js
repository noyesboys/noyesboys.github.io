const config = {
  type: Phaser.AUTO,
  width: 800,
  height: 600,
  pixelArt: true,
  physics: {
    default: 'arcade'
  },
  scene: {
    preload,
    create,
    update
  }
};

const game = new Phaser.Game(config);

let player;
let cursors;
let keyE;

let plants = [];
let interactText;
let uiText;

function preload() {
  this.load.image('player', 'https://labs.phaser.io/assets/sprites/phaser-dude.png');

  this.load.image('plant1', 'assets/plants/stage1.png');
  this.load.image('plant2', 'assets/plants/stage2.png');
  this.load.image('plant3', 'assets/plants/stage3.png');

  this.load.image('floor', 'https://labs.phaser.io/assets/tilemaps/tiles/gridtiles.png');
}

function create() {
  const mapWidth = 1600;
  const mapHeight = 1200;

  // Floor
  for (let x = 0; x < mapWidth; x += 32) {
    for (let y = 0; y < mapHeight; y += 32) {
      this.add.image(x, y, 'floor').setOrigin(0);
    }
  }

  // Player
  player = this.physics.add.sprite(400, 500, 'player');
  player.setCollideWorldBounds(true);
  player.setScale(0.6);

  // Camera follows player
  this.cameras.main.setBounds(0, 0, mapWidth, mapHeight);
  this.cameras.main.startFollow(player);

  // Controls
  cursors = this.input.keyboard.createCursorKeys();
  keyE = this.input.keyboard.addKey(Phaser.Input.Keyboard.KeyCodes.E);

  // Create multiple plants
  createPlant(this, 400, 300);
  createPlant(this, 500, 300);
  createPlant(this, 600, 300);

  // UI
  interactText = this.add.text(0, 0, "Press E", {
    fontSize: "12px",
    fill: "#00ff88"
  }).setScrollFactor(0).setVisible(false);

  uiText = this.add.text(10, 10, "", {
    fontSize: "14px",
    fill: "#ffffff"
  }).setScrollFactor(0);
}

function update() {
  handleMovement();
  handlePlants();
}

function handleMovement() {
  const speed = 200;
  player.setVelocity(0);

  if (cursors.left.isDown) player.setVelocityX(-speed);
  if (cursors.right.isDown) player.setVelocityX(speed);
  if (cursors.up.isDown) player.setVelocityY(-speed);
  if (cursors.down.isDown) player.setVelocityY(speed);
}

function createPlant(scene, x, y) {
  const sprite = scene.physics.add.staticImage(x, y, 'plant1').setScale(2);

  const plant = {
    sprite,
    stage: 1,
    water: 100,
    health: 100
  };

  plants.push(plant);
}

function handlePlants() {
  let nearPlant = null;

  plants.forEach(p => {
    const dist = Phaser.Math.Distance.Between(player.x, player.y, p.sprite.x, p.sprite.y);

    // Water drains over time
    p.water -= 0.02;

    if (p.water < 30) p.health -= 0.01;
    if (p.health < 0) p.health = 0;

    // Growth condition
    if (p.water > 60 && p.health > 60) {
      if (Math.random() < 0.001) {
        p.stage = Math.min(p.stage + 1, 3);
        p.sprite.setTexture('plant' + p.stage);
      }
    }

    if (dist < 80) {
      nearPlant = p;
    }
  });

  if (nearPlant) {
    interactText.setPosition(350, 500);
    interactText.setVisible(true);

    uiText.setText(
      `Stage: ${nearPlant.stage}\nWater: ${nearPlant.water.toFixed(0)}\nHealth: ${nearPlant.health.toFixed(0)}`
    );

    if (Phaser.Input.Keyboard.JustDown(keyE)) {
      waterPlant(nearPlant);
    }

  } else {
    interactText.setVisible(false);
    uiText.setText("");
  }
}

function waterPlant(plant) {
  plant.water = Math.min(plant.water + 30, 100);
}